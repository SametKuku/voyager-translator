<?php

namespace SametKuku\VoyagerTranslator\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SametKuku\VoyagerTranslator\Helpers\HtmlProtector;
use SametKuku\VoyagerTranslator\Helpers\SlugHelper;
use SametKuku\VoyagerTranslator\Helpers\SqlParser;
use SametKuku\VoyagerTranslator\Services\GeminiTranslator;
use SametKuku\VoyagerTranslator\Services\GoogleTranslator;
use SametKuku\VoyagerTranslator\Services\LangFileScanner;
use SametKuku\VoyagerTranslator\Services\LangFileWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TranslatorController extends Controller
{
    private const CACHE_TTL = 7200; // 2 hours

    // -------------------------------------------------------------------------
    // Views
    // -------------------------------------------------------------------------

    public function index(): \Illuminate\View\View
    {
        return view('voyager-translator::index');
    }

    // -------------------------------------------------------------------------
    // Data loading
    // -------------------------------------------------------------------------

    public function loadFromDb(): JsonResponse
    {
        try {
            $rows = DB::table('translations')
                ->select('table_name', 'column_name', 'foreign_key', 'locale', 'value')
                ->get();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Cannot read translations table: ' . $e->getMessage()], 500);
        }

        $groups = [];
        foreach ($rows as $row) {
            $key = "{$row->table_name}:{$row->column_name}:{$row->foreign_key}";
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'table_name'  => $row->table_name,
                    'column_name' => $row->column_name,
                    'foreign_key' => (string) $row->foreign_key,
                    'locales'     => [],
                ];
            }
            $groups[$key]['locales'][$row->locale] = $row->value ?? '';
        }

        $groups        = array_values($groups);
        $id            = Str::random(20);
        $localeCounts  = $this->countLocales($groups);
        $detectedLang  = array_key_first($localeCounts) ?? 'en';

        Cache::put("vt_{$id}_groups", $groups, self::CACHE_TTL);

        return response()->json([
            'success'       => true,
            'id'            => $id,
            'total'         => count($groups),
            'locale_stats'  => $localeCounts,
            'detected_lang' => $detectedLang,
        ]);
    }

    public function uploadSql(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|max:204800']); // 200 MB

        $sql    = file_get_contents($request->file('file')->getRealPath());
        $parser = new SqlParser();

        $rawGroups    = $parser->parseTranslations($sql);
        $modelData    = $parser->parseModelTables($sql);
        $modelLang    = $parser->detectModelLanguage($modelData);
        $localeCounts = $parser->getLocaleCounts($rawGroups);
        $detectedLang = !empty($localeCounts) ? (string) array_key_first($localeCounts) : $modelLang;

        $groups = array_values($rawGroups);
        $id     = Str::random(20);

        Cache::put("vt_{$id}_groups", $groups, self::CACHE_TTL);

        return response()->json([
            'success'       => true,
            'id'            => $id,
            'total'         => count($groups),
            'locale_stats'  => $localeCounts,
            'detected_lang' => $detectedLang,
            'model_lang'    => $modelLang,
        ]);
    }

    // -------------------------------------------------------------------------
    // Translation
    // -------------------------------------------------------------------------

    public function translateBatch(Request $request): JsonResponse
    {
        $v = $request->validate([
            'id'          => 'required|string',
            'source_lang' => 'required|string',
            'locale'      => 'required|string',
            'batch_index' => 'required|integer|min:0',
            'batch_size'  => 'required|integer|min:1|max:100',
            'engine'      => 'nullable|string|in:gemini,gtx',
            'gemini_key'  => 'nullable|string',
        ]);

        $id         = $v['id'];
        $sourceLang = $v['source_lang'];
        $locale     = $v['locale'];
        $batchIndex = (int) $v['batch_index'];
        $batchSize  = (int) $v['batch_size'];
        $engine     = $v['engine'] ?? config('voyager-translator.engine', 'gtx');
        $geminiKey  = $v['gemini_key'] ?? config('voyager-translator.gemini_api_key');

        $groups = Cache::get("vt_{$id}_groups");
        if (!$groups) {
            return response()->json(['success' => false, 'error' => 'Session expired. Please reload data.'], 400);
        }

        // Build flat list of groups that have source content
        $filtered = array_values(
            array_filter($groups, fn($g) => !empty($g['locales'][$sourceLang] ?? ''))
        );

        $total  = count($filtered);
        $offset = $batchIndex * $batchSize;

        if ($offset >= $total) {
            return response()->json(['success' => true, 'done' => true, 'total' => $total]);
        }

        $batch = array_slice($filtered, $offset, $batchSize);

        $translator = $this->buildTranslator($engine, $geminiKey);
        if (!$translator) {
            return response()->json(['success' => false, 'error' => 'Could not initialize translator. Check API key.'], 400);
        }

        $sourceTexts = array_map(fn($g) => (string) $g['locales'][$sourceLang], $batch);
        $isHtml      = (bool) collect($sourceTexts)->first(fn($t) => preg_match('/<[a-zA-Z][^>]*>/', $t));

        try {
            if ($isHtml) {
                $translated = [];
                foreach ($sourceTexts as $text) {
                    $protector    = new HtmlProtector();
                    $masked       = $protector->protect($text);
                    $result       = $translator->translateBatch([$masked], $locale)[0] ?? $text;
                    $translated[] = $protector->restore($result);
                }
            } else {
                $translated = $translator->translateBatch($sourceTexts, $locale);
            }

            // Post-process slug columns
            foreach ($batch as $i => $group) {
                if (($group['column_name'] ?? '') === 'slug') {
                    $translated[$i] = SlugHelper::slugify(
                        $translated[$i] ?? '',
                        (string) $group['locales'][$sourceLang]
                    );
                }
            }

            // Persist results in cache (keyed by "table:col:fk")
            $cacheKey = "vt_{$id}_results_{$locale}";
            $existing = Cache::get($cacheKey, []);

            foreach ($batch as $i => $group) {
                $gk          = "{$group['table_name']}:{$group['column_name']}:{$group['foreign_key']}";
                $existing[$gk] = $translated[$i] ?? $sourceTexts[$i];
            }

            Cache::put($cacheKey, $existing, self::CACHE_TTL);

            $done = ($offset + count($batch)) >= $total;

            return response()->json([
                'success'    => true,
                'done'       => $done,
                'batch_done' => $offset + count($batch),
                'total'      => $total,
                'progress'   => (int) round(($offset + count($batch)) / $total * 100),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Save to DB
    // -------------------------------------------------------------------------

    public function saveToDb(Request $request): JsonResponse
    {
        $request->validate([
            'id'      => 'required|string',
            'locales' => 'required|array',
        ]);

        $id      = $request->input('id');
        $locales = $request->input('locales');
        $groups  = Cache::get("vt_{$id}_groups");

        if (!$groups) {
            return response()->json(['success' => false, 'error' => 'Session expired.'], 400);
        }

        // Build index: "table:col:fk" → group
        $idx = [];
        foreach ($groups as $g) {
            $idx["{$g['table_name']}:{$g['column_name']}:{$g['foreign_key']}"] = $g;
        }

        $saved = 0;
        $now   = now()->toDateTimeString();

        foreach ($locales as $locale) {
            $results = Cache::get("vt_{$id}_results_{$locale}", []);
            foreach ($results as $gk => $value) {
                if (empty($value)) continue;
                $g = $idx[$gk] ?? null;
                if (!$g) continue;

                DB::table('translations')->updateOrInsert(
                    [
                        'table_name'  => $g['table_name'],
                        'column_name' => $g['column_name'],
                        'foreign_key' => $g['foreign_key'],
                        'locale'      => $locale,
                    ],
                    ['value' => $value, 'updated_at' => $now, 'created_at' => $now]
                );
                $saved++;
            }
        }

        return response()->json(['success' => true, 'saved' => $saved]);
    }

    // -------------------------------------------------------------------------
    // Exports
    // -------------------------------------------------------------------------

    public function exportSql(Request $request): StreamedResponse
    {
        $id      = $request->query('id', '');
        $locales = array_filter(explode(',', $request->query('locales', '')));
        $groups  = Cache::get("vt_{$id}_groups", []);

        $idx = [];
        foreach ($groups as $g) {
            $idx["{$g['table_name']}:{$g['column_name']}:{$g['foreign_key']}"] = $g;
        }

        $now   = now()->toDateTimeString();
        $lines = ["-- Voyager Translator Export", "-- Generated: {$now}", ""];

        foreach ($locales as $locale) {
            $results = Cache::get("vt_{$id}_results_{$locale}", []);
            foreach ($results as $gk => $value) {
                if (empty($value)) continue;
                $g = $idx[$gk] ?? null;
                if (!$g) continue;

                $tbl = addslashes($g['table_name']);
                $col = addslashes($g['column_name']);
                $fk  = (int) $g['foreign_key'];
                $val = addslashes($value);
                $loc = addslashes($locale);

                $lines[] = "INSERT INTO `translations` (`table_name`,`column_name`,`foreign_key`,`locale`,`value`,`created_at`,`updated_at`) "
                    . "VALUES ('{$tbl}','{$col}',{$fk},'{$loc}','{$val}','{$now}','{$now}') "
                    . "ON DUPLICATE KEY UPDATE `value`='{$val}',`updated_at`='{$now}';";
            }
        }

        $sql = implode("\n", $lines);

        return response()->streamDownload(
            fn() => print($sql),
            'translations.sql',
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }

    public function exportJson(Request $request): StreamedResponse
    {
        $id      = $request->query('id', '');
        $locales = array_filter(explode(',', $request->query('locales', '')));
        $groups  = Cache::get("vt_{$id}_groups", []);

        $idx = [];
        foreach ($groups as $g) {
            $idx["{$g['table_name']}:{$g['column_name']}:{$g['foreign_key']}"] = $g;
        }

        $out = [];
        foreach ($locales as $locale) {
            $results = Cache::get("vt_{$id}_results_{$locale}", []);
            foreach ($results as $gk => $value) {
                $g = $idx[$gk] ?? null;
                if (!$g) continue;
                $out[$locale][$g['table_name']][$g['foreign_key']][$g['column_name']] = $value;
            }
        }

        $json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(
            fn() => print($json),
            'translations.json',
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    // =========================================================================
    // Lang File Mode
    // =========================================================================

    /**
     * Scan lang/ directory and return metadata.
     */
    public function scanLang(): JsonResponse
    {
        $langPath = lang_path();
        $scanner  = new LangFileScanner();
        $info     = $scanner->scan($langPath);

        return response()->json([
            'success'  => true,
            'path'     => $langPath,
            'locales'  => $info['locales'],
            'php_files'=> $info['phpFiles'],
            'stats'    => $info['stats'],
        ]);
    }

    /**
     * Load all lang strings for the chosen source locale into cache.
     */
    public function loadLang(Request $request): JsonResponse
    {
        $request->validate(['source_lang' => 'required|string']);

        $langPath    = lang_path();
        $sourceLang  = $request->input('source_lang');
        $scanner     = new LangFileScanner();
        $strings     = $scanner->readLocale($langPath, $sourceLang);

        if (empty($strings)) {
            return response()->json(['success' => false, 'error' => "No strings found for locale '{$sourceLang}' in {$langPath}"], 400);
        }

        $id = Str::random(20);
        Cache::put("vt_{$id}_lang_strings", $strings, self::CACHE_TTL);

        return response()->json([
            'success'     => true,
            'id'          => $id,
            'total'       => count($strings),
            'source_lang' => $sourceLang,
        ]);
    }

    /**
     * Translate one batch of lang strings.
     */
    public function translateLangBatch(Request $request): JsonResponse
    {
        $v = $request->validate([
            'id'          => 'required|string',
            'locale'      => 'required|string',
            'batch_index' => 'required|integer|min:0',
            'batch_size'  => 'required|integer|min:1|max:200',
            'engine'      => 'nullable|string|in:gemini,gtx',
            'gemini_key'  => 'nullable|string',
        ]);

        $id         = $v['id'];
        $locale     = $v['locale'];
        $batchIndex = (int) $v['batch_index'];
        $batchSize  = (int) $v['batch_size'];
        $engine     = $v['engine'] ?? config('voyager-translator.engine', 'gtx');
        $geminiKey  = $v['gemini_key'] ?? config('voyager-translator.gemini_api_key');

        $strings = Cache::get("vt_{$id}_lang_strings");
        if (!$strings) {
            return response()->json(['success' => false, 'error' => 'Session expired. Please reload.'], 400);
        }

        $keys   = array_keys($strings);
        $total  = count($keys);
        $offset = $batchIndex * $batchSize;

        if ($offset >= $total) {
            return response()->json(['success' => true, 'done' => true, 'total' => $total]);
        }

        $batchKeys   = array_slice($keys, $offset, $batchSize);
        $batchValues = array_map(fn($k) => $strings[$k], $batchKeys);

        $translator = $this->buildTranslator($engine, $geminiKey);
        if (!$translator) {
            return response()->json(['success' => false, 'error' => 'Could not initialize translator. Check API key.'], 400);
        }

        // Protect Laravel placeholders (:attribute, :name, etc.) + HTML
        $protectors  = [];
        $maskedValues = [];
        foreach ($batchValues as $i => $text) {
            $p = new HtmlProtector();
            // Also protect :placeholder tokens common in lang files
            $masked = preg_replace_callback('/:[a-z_]+\b/', function ($m) use ($p) {
                return $p->protect($m[0]);
            }, $p->protect($text));
            $protectors[$i]   = $p;
            $maskedValues[$i] = $masked;
        }

        try {
            $translated = $translator->translateBatch(array_values($maskedValues), $locale);

            // Restore protected tokens
            foreach ($translated as $i => $text) {
                $translated[$i] = $protectors[$i]->restore($text);
            }

            // Persist results
            $cacheKey = "vt_{$id}_lang_results_{$locale}";
            $existing = Cache::get($cacheKey, []);
            foreach ($batchKeys as $i => $key) {
                $existing[$key] = $translated[$i] ?? $batchValues[$i];
            }
            Cache::put($cacheKey, $existing, self::CACHE_TTL);

            $done = ($offset + count($batchKeys)) >= $total;

            return response()->json([
                'success'  => true,
                'done'     => $done,
                'progress' => (int) round(($offset + count($batchKeys)) / $total * 100),
                'total'    => $total,
                'done_count' => $offset + count($batchKeys),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Write translated files directly to lang/.
     */
    public function writeLangFiles(Request $request): JsonResponse
    {
        $request->validate([
            'id'      => 'required|string',
            'locales' => 'required|array',
        ]);

        $id      = $request->input('id');
        $locales = $request->input('locales');
        $writer  = new LangFileWriter();

        $written = 0;
        foreach ($locales as $locale) {
            $results = Cache::get("vt_{$id}_lang_results_{$locale}", []);
            if (empty($results)) continue;

            try {
                $writer->writeLocale(lang_path(), $locale, $results);
                $written += count($results);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'error' => "Write failed for {$locale}: " . $e->getMessage()], 500);
            }
        }

        return response()->json(['success' => true, 'written' => $written]);
    }

    /**
     * Export all translated lang files as a ZIP archive.
     */
    public function exportLangZip(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        if (!class_exists(\ZipArchive::class)) {
            return response()->json(['error' => 'ZipArchive PHP extension is not available.'], 500);
        }

        $id      = $request->query('id', '');
        $locales = array_filter(explode(',', $request->query('locales', '')));
        $writer  = new LangFileWriter();
        $tmpFile = tempnam(sys_get_temp_dir(), 'vt_lang_') . '.zip';

        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($locales as $locale) {
            $results = Cache::get("vt_{$id}_lang_results_{$locale}", []);
            if (empty($results)) continue;

            $fileMap = $writer->buildFileMap($locale, $results);
            foreach ($fileMap as $path => $content) {
                $zip->addFromString($path, $content);
            }
        }

        $zip->close();

        return response()->streamDownload(function () use ($tmpFile) {
            readfile($tmpFile);
            @unlink($tmpFile);
        }, 'lang-translations.zip', ['Content-Type' => 'application/zip']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function countLocales(array $groups): array
    {
        $counts = [];
        foreach ($groups as $g) {
            foreach ($g['locales'] as $locale => $val) {
                if (!empty($val)) {
                    $counts[$locale] = ($counts[$locale] ?? 0) + 1;
                }
            }
        }
        arsort($counts);
        return $counts;
    }

    private function buildTranslator(string $engine, ?string $geminiKey): GeminiTranslator|GoogleTranslator|null
    {
        if ($engine === 'gemini') {
            $key = $geminiKey ?: config('voyager-translator.gemini_api_key');
            if (empty($key)) return null;
            return new GeminiTranslator(
                $key,
                config('voyager-translator.gemini_model', 'gemini-2.5-flash'),
                (int) config('voyager-translator.batch_size', 40)
            );
        }
        return new GoogleTranslator();
    }
}
