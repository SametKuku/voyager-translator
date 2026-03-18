# Laravel Translator

Auto-translate your entire Laravel application — **lang/ files** (PHP + JSON) and **Voyager CMS** database content — using **Gemini AI** or **Google Translate**. Supports 15 languages with a built-in web UI.

---

## Requirements

- PHP 8.0+
- Laravel 10, 11, 12 or 13

---

## Installation

```bash
composer require laratoolkit/laravel-translator
```

> **PHP 8.5+ or dependency conflicts?**
> ```bash
> composer require laratoolkit/laravel-translator --ignore-platform-reqs
> ```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=laravel-translator-config
```

---

## Web UI

Open your browser at:

```
http://your-app.test/laravel-translator
```

### Lang Files Tab
Translate your `lang/` PHP and JSON files:

1. **Scan** — detects all locales and key counts in your `lang/` directory
2. **Load Strings** — loads all translatable strings for the selected source locale
3. **Select targets** — choose which languages to translate into
4. **Choose engine** — Google Translate (free) or Gemini AI
5. **Translate** — real-time per-language progress bar
6. **Write to disk** — saves directly to `lang/{locale}/` &nbsp;OR&nbsp; **Download ZIP** — review before writing

### Voyager / DB Tab
Translate Voyager CMS `translations` table entries (or any model table content via SQL dump):

1. **Load from DB** or **Upload SQL** dump
2. Source language auto-detected; select target languages
3. Translate with real-time progress
4. **Save to DB**, **Download SQL**, or **Download JSON**

> Add `'auth'` to the `middleware` config key to protect the UI route.

---

## Artisan Command

```bash
# Translate lang/ files
php artisan translate --mode=files --from=en --to=tr,es,ru,ar

# Translate Voyager DB
php artisan translate --mode=voyager --from=tr --to=en,es,ru

# Use Gemini AI
php artisan translate --mode=files --engine=gemini --from=en --to=tr,es

# Only translate missing keys
php artisan translate --only-missing

# Preview without writing
php artisan translate --dry-run
```

---

## Configuration

```env
# Engine: gemini or gtx (default: gtx)
LARAVEL_TRANSLATOR_ENGINE=gemini

# Required if engine=gemini
GEMINI_API_KEY=your_key_here

# Source locale (auto-detected in web UI)
LARAVEL_TRANSLATOR_SOURCE=en

# Target locales for Artisan command
LARAVEL_TRANSLATOR_TARGETS=tr,es,ru,ar

# Web UI route prefix (default: laravel-translator)
LARAVEL_TRANSLATOR_PREFIX=laravel-translator
```

---

## Supported Languages

| Code | Language   | Code | Language   |
|------|------------|------|------------|
| tr   | Turkish    | pt   | Portuguese |
| en   | English    | it   | Italian    |
| es   | Spanish    | ja   | Japanese   |
| ru   | Russian    | ko   | Korean     |
| de   | German     | nl   | Dutch      |
| fr   | French     | pl   | Polish     |
| ar   | Arabic     | uk   | Ukrainian  |
| zh   | Chinese    |      |            |

---

## How It Works

### Lang Files Mode
1. Scans `lang/{locale}/` directories for PHP files and JSON files
2. Flattens nested arrays to `file.key` → `value` pairs
3. Protects Laravel placeholders (`:attribute`, `:name`) and HTML tags during translation
4. Reconstructs nested structure and writes valid PHP array files or JSON files

### Voyager / DB Mode
1. Reads rows from the `translations` table (or parses a SQL dump)
2. Falls back to model table content if `translations` table is absent
3. Detects source language automatically from content
4. HTML-safe translation with token protection
5. Slug columns auto-transliterated (Turkish, Arabic, Cyrillic → Latin)
6. Saves via `updateOrInsert` or exports as SQL / JSON

---

## Engines

### Google Translate (GTX) — Free
No API key required. Works out of the box.

### Gemini AI — Fast & Accurate
Uses `gemini-2.5-flash` with bulk requests (up to 60 items/call).  
Get a free API key at [Google AI Studio](https://aistudio.google.com/app/apikey).

---

## License

MIT
