<?php

namespace SametKuku\VoyagerTranslator\Services;

class LangFileWriter
{
    /**
     * Write translated flat strings back to lang/{locale}/ files.
     *
     * @param string $langPath   e.g. base_path('lang')
     * @param string $locale     e.g. 'tr'
     * @param array  $translated flat key=>value ['auth.failed' => '...', '__json__.Hello' => '...']
     */
    public function writeLocale(string $langPath, string $locale, array $translated): void
    {
        [$phpStrings, $jsonStrings] = $this->splitBySource($translated);

        // PHP files
        $phpNested = $this->unflatten($phpStrings);
        foreach ($phpNested as $file => $data) {
            $dir = $langPath . DIRECTORY_SEPARATOR . $locale;
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents(
                $dir . DIRECTORY_SEPARATOR . $file . '.php',
                $this->toPhpFile($data)
            );
        }

        // JSON file
        if (!empty($jsonStrings)) {
            file_put_contents(
                $langPath . DIRECTORY_SEPARATOR . $locale . '.json',
                json_encode($jsonStrings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }
    }

    /**
     * Build an in-memory array of file contents for ZIP export.
     * Returns: [ 'tr/auth.php' => '<?php return [...];', 'tr.json' => '{...}' ]
     */
    public function buildFileMap(string $locale, array $translated): array
    {
        [$phpStrings, $jsonStrings] = $this->splitBySource($translated);

        $files = [];

        $phpNested = $this->unflatten($phpStrings);
        foreach ($phpNested as $file => $data) {
            $files["{$locale}/{$file}.php"] = $this->toPhpFile($data);
        }

        if (!empty($jsonStrings)) {
            $files["{$locale}.json"] = json_encode(
                $jsonStrings,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        return $files;
    }

    /**
     * Separate flat strings into PHP-file strings and JSON strings.
     * JSON keys start with "__json__."
     */
    private function splitBySource(array $flat): array
    {
        $php  = [];
        $json = [];

        foreach ($flat as $key => $value) {
            if (str_starts_with($key, '__json__.')) {
                $json[substr($key, strlen('__json__.'))] = $value;
            } else {
                $php[$key] = $value;
            }
        }

        return [$php, $json];
    }

    /**
     * Convert a flat dot-notation array back to nested, grouping by first key segment (file name).
     * 'auth.failed' => 'text'  →  ['auth' => ['failed' => 'text']]
     */
    public function unflatten(array $flat): array
    {
        $nested = [];

        foreach ($flat as $key => $value) {
            $parts = explode('.', $key);
            $node  = &$nested;
            foreach ($parts as $part) {
                if (!isset($node[$part])) {
                    $node[$part] = [];
                }
                $node = &$node[$part];
            }
            $node = $value;
            unset($node);
        }

        return $nested;
    }

    /**
     * Generate a valid PHP lang file string.
     */
    public function toPhpFile(array $data): string
    {
        $inner = $this->renderArray($data, 1);
        return "<?php\n\nreturn [\n{$inner}];\n";
    }

    private function renderArray(array $data, int $depth): string
    {
        $indent = str_repeat('    ', $depth);
        $lines  = [];

        foreach ($data as $key => $value) {
            $escapedKey = var_export((string) $key, true);
            if (is_array($value)) {
                $inner   = $this->renderArray($value, $depth + 1);
                $lines[] = "{$indent}{$escapedKey} => [\n{$inner}{$indent}],";
            } else {
                $escapedVal = $this->exportString((string) $value);
                $lines[]    = "{$indent}{$escapedKey} => {$escapedVal},";
            }
        }

        return implode("\n", $lines) . (count($lines) ? "\n" : '');
    }

    /**
     * Export a string, using single quotes when possible, double quotes otherwise.
     */
    private function exportString(string $value): string
    {
        // Single-quote safe (no single quotes or backslashes in value)
        if (!str_contains($value, "'") && !str_contains($value, '\\')) {
            return "'" . $value . "'";
        }
        // Fall back to double-quoted with escaping
        $escaped = str_replace(['\\', '"', '$', "\n", "\r", "\t"], ['\\\\', '\\"', '\\$', '\\n', '\\r', '\\t'], $value);
        return '"' . $escaped . '"';
    }
}
