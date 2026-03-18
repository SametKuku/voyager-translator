<?php

namespace SametKuku\VoyagerTranslator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SametKuku\VoyagerTranslator\Helpers\HtmlProtector;
use SametKuku\VoyagerTranslator\Helpers\SlugHelper;
use SametKuku\VoyagerTranslator\Services\GeminiTranslator;
use SametKuku\VoyagerTranslator\Services\GoogleTranslator;
use SametKuku\VoyagerTranslator\Services\LangFileScanner;
use SametKuku\VoyagerTranslator\Services\LangFileWriter;

class TranslateCommand extends Command
{
    protected $signature = 'translate
        {--mode=files : "files" (lang/) or "voyager" (database)}
        {--from= : Source locale}
        {--to= : Target locales comma-separated (e.g. tr,es,ru,ar)}
        {--engine= : gemini or gtx}
        {--only-missing : Only translate missing keys/rows}
        {--dry-run : Preview without writing anything}';

    protected $description = 'Auto-translate Laravel lang/ files or Voyager DB — Gemini AI or Google Translate';

    private const HTML_PATTERN = '/<[a-zA-Z][^>]*>|<\/[a-zA-Z]+>/';

    public function handle(): int
    {
        $mode        = $this->option('mode') ?? 'files';
        $engine      = $this->option('engine') ?? config('voyager-translator.engine', 'gtx');
        $sourceLang  = $this->option('from')   ?? config('voyager-translator.source_locale', 'en');
        $targetInput = $this->option('to')     ?? config('voyager-translator.target_locales', 'tr,es,ru,ar');
        $targetLangs = array_values(array_filter(array_map('trim', explode(',', $targetInput))));
        $dryRun      = $this->option('dry-run');

        $this->newLine();
        $this->info("  Laravel Translator");
        $this->line("  Mode    : <fg=cyan>{$mode}</>");
        $this->line("  Engine  : <fg=cyan>{$engine}</>");
        $this->line("  Source  : <fg=yellow>{$sourceLang}</>");
        $this->line("  Targets : <fg=green>" . implode(', ', $targetLangs) . "</>");
        $dryRun && $this->warn("  ⚠  DRY RUN — nothing will be saved.");
        $this->newLine();

        if ($mode === 'files') {
            return $this->handleLangFiles($sourceLang, $targetLangs, $engine, $dryRun);
        }

        return $this->handleVoyager($sourceLang, $targetLangs, $engine, $dryRun);
    }

    // =========================================================================
    // Lang Files Mode
    // =========================================================================

    private function handleLangFiles(string $sourceLang, array $targetLangs, string $engine, bool $dryRun): int
    {
        $langPath = lang_path();
        $scanner  = new LangFileScanner();
        $writer   = new LangFileWriter();

        $strings = $scanner->readLocale($langPath, $sourceLang);

        if (empty($strings)) {
            $this->error("No strings found for locale '{$sourceLang}' in {$langPath}");
            return self::FAILURE;
        }

        $this->info("Found <fg=cyan>" . count($strings) . "</> strings in <fg=yellow>{$sourceLang}</>");

        $translator = $this->buildTranslator($engine);
        if (!$translator) return self::FAILURE;

        $batchSize = (int) config('voyager-translator.batch_size', 60);
        $onlyMissing = $this->option('only-missing');

        foreach ($targetLangs as $locale) {
            $this->newLine();
            $this->info("Translating → <fg=green>{$locale}</> ...");

            $existing = $onlyMissing ? $scanner->readLocale($langPath, $locale) : [];
            $toTranslate = $onlyMissing
                ? array_filter($strings, fn($v, $k) => empty($existing[$k] ?? ''), ARRAY_FILTER_USE_BOTH)
                : $strings;

            if (empty($toTranslate)) {
                $this->line("  All {$locale} strings already exist. Skipping.");
                continue;
            }

            $keys   = array_keys($toTranslate);
            $values = array_values($toTranslate);
            $total  = count($keys);
            $bar    = $this->output->createProgressBar($total);
            $bar->start();

            $translated = [];
            for ($i = 0; $i < $total; $i += $batchSize) {
                $batchKeys   = array_slice($keys, $i, $batchSize);
                $batchValues = array_slice($values, $i, $batchSize);

                try {
                    // Protect :placeholders + HTML
                    $protectors   = [];
                    $maskedValues = [];
                    foreach ($batchValues as $j => $text) {
                        $p = new HtmlProtector();
                        $masked = preg_replace_callback('/:[a-z_]+\b/', fn($m) => $p->protect($m[0]), $p->protect($text));
                        $protectors[$j]   = $p;
                        $maskedValues[$j] = $masked;
                    }

                    $results = $translator->translateBatch($maskedValues, $locale);

                    foreach ($results as $j => $text) {
                        $translated[$batchKeys[$j]] = $protectors[$j]->restore($text);
                        $bar->advance();
                    }
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->warn("  Batch error: " . $e->getMessage() . " — retrying in 5s...");
                    sleep(5);
                    $i -= $batchSize;
                }
            }

            $bar->finish();
            $this->newLine();

            if (!$dryRun) {
                // Merge with existing strings
                $allStrings = array_merge($existing, $translated);
                $writer->writeLocale($langPath, $locale, $allStrings);
                $this->info("  ✓ {$locale} — written " . count($translated) . " strings to lang/{$locale}/");
            } else {
                $this->info("  ✓ {$locale} — would write " . count($translated) . " strings (dry run)");
            }
        }

        $this->newLine();
        $this->info($dryRun ? "✓ Dry run complete." : "✓ All lang files written.");
        return self::SUCCESS;
    }

    // =========================================================================
    // Voyager / DB Mode
    // =========================================================================

    private function handleVoyager(string $sourceLang, array $targetLangs, string $engine, bool $dryRun): int
    {
        $onlyMissing = $this->option('only-missing');
        $translator  = $this->buildTranslator($engine);
        if (!$translator) return self::FAILURE;

        $groups = $this->loadVoyagerGroups($sourceLang);
        $this->info("Found <fg=cyan>" . count($groups) . "</> translation groups.");

        if (empty($groups)) {
            $this->warn("No content found for source locale '{$sourceLang}' in translations table.");
            return self::SUCCESS;
        }

        $batchSize = (int) config('voyager-translator.batch_size', 40);

        foreach ($targetLangs as $locale) {
            $this->newLine();
            $this->info("Translating → <fg=green>{$locale}</> ...");

            $toTranslate = $onlyMissing
                ? array_values(array_filter($groups, fn($g) => empty($g['translations'][$locale] ?? '')))
                : array_values($groups);

            $total = count($toTranslate);
            if ($total === 0) {
                $this->line("  All {$locale} translations exist. Skipping.");
                continue;
            }

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            for ($i = 0; $i < $total; $i += $batchSize) {
                $batch       = array_slice($toTranslate, $i, $batchSize);
                $sourceTexts = array_column($batch, 'source_value');
                $isHtml      = (bool) array_filter($sourceTexts, fn($t) => preg_match(self::HTML_PATTERN, $t));

                try {
                    if ($isHtml) {
                        $translated = [];
                        foreach ($sourceTexts as $text) {
                            $p          = new HtmlProtector();
                            $masked     = $p->protect($text);
                            $result     = $translator->translateBatch([$masked], $locale)[0];
                            $translated[] = $p->restore($result);
                        }
                    } else {
                        $translated = $translator->translateBatch($sourceTexts, $locale);
                    }

                    foreach ($batch as $idx => $group) {
                        $value = $translated[$idx] ?? $group['source_value'];
                        if ($group['column_name'] === 'slug') {
                            $value = SlugHelper::slugify($value, $group['source_value']);
                        }
                        if (empty($value)) continue;

                        if (!$dryRun) {
                            DB::table('translations')->updateOrInsert(
                                ['table_name' => $group['table_name'], 'column_name' => $group['column_name'],
                                 'foreign_key' => $group['foreign_key'], 'locale' => $locale],
                                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
                            );
                        }
                        $bar->advance();
                    }
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->warn("  Batch error: " . $e->getMessage() . " — retrying...");
                    sleep(5);
                    $i -= $batchSize;
                }
            }

            $bar->finish();
            $this->newLine();
            $this->info("  ✓ {$locale} done.");
        }

        $this->newLine();
        $this->info($dryRun ? "✓ Dry run complete." : "✓ All translations saved to database.");
        return self::SUCCESS;
    }

    private function loadVoyagerGroups(string $sourceLang): array
    {
        $rows   = DB::table('translations')->where('locale', $sourceLang)->whereNotNull('value')->where('value', '!=', '')->get();
        $groups = [];

        foreach ($rows as $row) {
            $key = "{$row->table_name}:{$row->column_name}:{$row->foreign_key}";
            $existing = DB::table('translations')
                ->where('table_name', $row->table_name)->where('column_name', $row->column_name)
                ->where('foreign_key', $row->foreign_key)->whereNotIn('locale', [$sourceLang])
                ->pluck('value', 'locale')->toArray();

            $groups[$key] = [
                'table_name'   => $row->table_name, 'column_name'  => $row->column_name,
                'foreign_key'  => $row->foreign_key, 'source_value' => $row->value,
                'translations' => $existing,
            ];
        }
        return $groups;
    }

    private function buildTranslator(string $engine): GeminiTranslator|GoogleTranslator|null
    {
        if ($engine === 'gemini') {
            $key = config('voyager-translator.gemini_api_key');
            if (empty($key)) {
                $this->error("GEMINI_API_KEY is not set in .env");
                return null;
            }
            return new GeminiTranslator($key, config('voyager-translator.gemini_model', 'gemini-2.5-flash'), (int) config('voyager-translator.batch_size', 40));
        }
        return new GoogleTranslator();
    }
}
