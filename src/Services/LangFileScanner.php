<?php

namespace SametKuku\VoyagerTranslator\Services;

class LangFileScanner
{
    /**
     * Scan the lang/ directory and return metadata.
     *
     * Returns:
     * [
     *   'locales'      => ['en', 'tr', ...],
     *   'php_files'    => ['auth.php', 'pagination.php', ...],
     *   'json_locales' => ['en', ...],
     *   'stats'        => ['en' => 150, 'tr' => 40, ...],
     * ]
     */
    public function scan(string $langPath): array
    {
        if (!is_dir($langPath)) {
            return ['locales' => [], 'php_files' => [], 'json_locales' => [], 'stats' => []];
        }

        $locales    = [];
        $phpFiles   = [];
        $jsonLocales = [];

        // Subdirectory locales (lang/en/, lang/tr/, ...)
        foreach (scandir($langPath) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $full = $langPath . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($full)) {
                $locales[] = $entry;
                foreach (scandir($full) as $file) {
                    if (str_ends_with($file, '.php') && !in_array($file, $phpFiles)) {
                        $phpFiles[] = $file;
                    }
                }
            }

            // JSON files: lang/en.json, lang/tr.json
            if (str_ends_with($entry, '.json')) {
                $locale        = basename($entry, '.json');
                $jsonLocales[] = $locale;
                if (!in_array($locale, $locales)) {
                    $locales[] = $locale;
                }
            }
        }

        sort($locales);
        sort($phpFiles);
        sort($jsonLocales);

        // Count keys per locale
        $stats = [];
        foreach ($locales as $locale) {
            $stats[$locale] = count($this->readLocale($langPath, $locale));
        }

        return compact('locales', 'phpFiles', 'jsonLocales', 'stats');
    }

    /**
     * Read all translation strings for a locale as a flat key => value map.
     * Keys: "auth.failed", "pagination.previous", "custom.key.nested"
     * JSON keys are stored as-is (they're usually full sentences).
     */
    public function readLocale(string $langPath, string $locale): array
    {
        $flat = [];

        // PHP files (lang/en/auth.php → prefix "auth")
        $dir = $langPath . DIRECTORY_SEPARATOR . $locale;
        if (is_dir($dir)) {
            foreach (scandir($dir) as $file) {
                if (!str_ends_with($file, '.php')) continue;
                $data = @include("{$dir}/{$file}");
                if (!is_array($data)) continue;
                $prefix = basename($file, '.php');
                foreach ($this->flatten($data, $prefix) as $k => $v) {
                    $flat[$k] = $v;
                }
            }
        }

        // JSON file (lang/en.json)
        $jsonFile = $langPath . DIRECTORY_SEPARATOR . $locale . '.json';
        if (file_exists($jsonFile)) {
            $data = json_decode(file_get_contents($jsonFile), true);
            if (is_array($data)) {
                foreach ($this->flatten($data, '__json__') as $k => $v) {
                    $flat[$k] = $v;
                }
            }
        }

        return $flat;
    }

    /**
     * Flatten nested array with dot-notation keys.
     * e.g. ['auth' => ['failed' => 'text']] → ['auth.failed' => 'text']
     */
    public function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix !== '' ? $prefix . '.' . $key : (string) $key;
            if (is_array($value)) {
                foreach ($this->flatten($value, $fullKey) as $k => $v) {
                    $result[$k] = $v;
                }
            } elseif (is_string($value)) {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }

    /**
     * Return which PHP files exist for a specific locale.
     */
    public function getPhpFilesForLocale(string $langPath, string $locale): array
    {
        $dir = $langPath . DIRECTORY_SEPARATOR . $locale;
        if (!is_dir($dir)) return [];
        return array_filter(scandir($dir), fn($f) => str_ends_with($f, '.php'));
    }
}
