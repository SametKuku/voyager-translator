<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Laravel Translator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .fade-in { animation: fadeIn .25s ease-out; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(5px) } to { opacity:1; transform:none } }
    </style>
    <script>
        window.VTR = {
            scanLang:           "{{ route('voyager-translator.lang-scan') }}",
            loadLang:           "{{ route('voyager-translator.lang-load') }}",
            translateLangBatch: "{{ route('voyager-translator.lang-translate-batch') }}",
            writeLang:          "{{ route('voyager-translator.lang-write') }}",
            exportLangZip:      "{{ route('voyager-translator.lang-export') }}",
            loadDb:             "{{ route('voyager-translator.load-db') }}",
            uploadSql:          "{{ route('voyager-translator.upload-sql') }}",
            translateBatch:     "{{ route('voyager-translator.translate-batch') }}",
            save:               "{{ route('voyager-translator.save') }}",
            exportSql:          "{{ route('voyager-translator.export-sql') }}",
            exportJson:         "{{ route('voyager-translator.export-json') }}",
        };
    </script>
</head>
<body class="bg-slate-50 min-h-screen" x-data="app()" x-cloak>

{{-- ═══ HEADER ═══════════════════════════════════════════════════════════════ --}}
<header class="bg-gradient-to-r from-indigo-700 to-violet-700 text-white shadow-xl">
    <div class="max-w-4xl mx-auto px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 bg-white/15 rounded-xl flex items-center justify-center ring-1 ring-white/25 flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
            </svg>
        </div>
        <div class="flex-1">
            <h1 class="font-bold text-lg leading-tight">Laravel Translator</h1>
            <p class="text-xs text-indigo-200">Translate lang/ files &amp; Voyager DB — Gemini AI or Google Translate</p>
        </div>
    </div>

    {{-- Mode Tabs --}}
    <div class="max-w-4xl mx-auto px-5 flex gap-1 pb-0">
        <button @click="switchMode('lang')"
            :class="mode === 'lang'
                ? 'bg-white text-indigo-700 font-semibold shadow-sm'
                : 'text-indigo-200 hover:text-white hover:bg-white/10'"
            class="px-5 py-2.5 text-sm rounded-t-xl transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
            Lang Files
        </button>
        <button @click="switchMode('voyager')"
            :class="mode === 'voyager'
                ? 'bg-white text-indigo-700 font-semibold shadow-sm'
                : 'text-indigo-200 hover:text-white hover:bg-white/10'"
            class="px-5 py-2.5 text-sm rounded-t-xl transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
            </svg>
            Voyager / DB
        </button>
    </div>
</header>

<div class="max-w-4xl mx-auto px-4 py-7 space-y-5">

{{-- ════════════════════════════════════════════════════════════════════════════
     ▌ LANG FILES TAB
═════════════════════════════════════════════════════════════════════════════ --}}
<template x-if="mode === 'lang'">
<div class="space-y-5">

    {{-- Step 1 – Scan --}}
    <div class="bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <span class="step-badge">1</span>
            <h2 class="font-semibold text-slate-800">Scan lang/ Directory</h2>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
                <button @click="langScan()" :disabled="lang.scanning"
                    class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors flex items-center gap-2">
                    <svg x-show="lang.scanning" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span x-text="lang.scanning ? 'Scanning…' : 'Scan lang/'"></span>
                </button>
                <span class="text-xs text-slate-400 font-mono" x-text="lang.path || ''"></span>
            </div>

            <template x-if="lang.scanned">
                <div class="fade-in space-y-4">
                    {{-- Locale stats --}}
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(count, locale) in lang.stats" :key="locale">
                            <div class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-full text-xs">
                                <span class="font-mono font-bold text-slate-700" x-text="locale"></span>
                                <span class="text-slate-400" x-text="count + ' keys'"></span>
                            </div>
                        </template>
                        <template x-if="Object.keys(lang.stats).length === 0">
                            <p class="text-sm text-amber-600">No locales found. Make sure your lang/ directory has locale subdirectories.</p>
                        </template>
                    </div>

                    {{-- Source language picker --}}
                    <div class="flex flex-wrap items-center gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Source Locale</label>
                            <select x-model="lang.sourceLang"
                                class="border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-400 min-w-[200px]">
                                <template x-for="locale in lang.locales" :key="locale">
                                    <option :value="locale" x-text="langName(locale) + ' (' + locale + ')'"></option>
                                </template>
                            </select>
                        </div>
                        <div class="pt-5">
                            <button @click="langLoad()" :disabled="!lang.sourceLang || lang.loading"
                                class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg hover:bg-slate-900 disabled:opacity-40 transition-colors">
                                <span x-text="lang.loading ? 'Loading…' : 'Load Strings'"></span>
                            </button>
                        </div>
                    </div>

                    <template x-if="lang.loaded">
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 text-sm text-emerald-800 flex items-center gap-2 fade-in">
                            <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span>Loaded <strong x-text="lang.totalStrings.toLocaleString()"></strong> strings from <strong x-text="lang.sourceLang"></strong></span>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="lang.error">
                <div class="mt-3 text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl px-4 py-3 fade-in" x-text="lang.error"></div>
            </template>
        </div>
    </div>

    {{-- Step 2 – Target Languages --}}
    <template x-if="lang.loaded">
    <div class="bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <span class="step-badge">2</span>
            <h2 class="font-semibold text-slate-800">Target Languages</h2>
        </div>
        <div class="p-6">
            <div class="flex flex-wrap gap-2">
                <template x-for="l in langs" :key="l.code">
                    <button @click="langToggleTarget(l.code)"
                        :disabled="l.code === lang.sourceLang"
                        :class="lang.targets.includes(l.code)
                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                            : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-400'"
                        class="px-3.5 py-1.5 text-sm border rounded-full transition-all disabled:opacity-20 disabled:cursor-not-allowed">
                        <span x-text="l.flag + ' ' + l.name"></span>
                    </button>
                </template>
            </div>
            <p x-show="lang.targets.length === 0" class="text-xs text-amber-600 mt-2">Select at least one target language</p>
        </div>
    </div>
    </template>

    {{-- Step 3 – Engine --}}
    <template x-if="lang.loaded && lang.targets.length > 0">
    <div class="bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <span class="step-badge">3</span>
            <h2 class="font-semibold text-slate-800">Translation Engine</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-3 mb-4">
                <button @click="engine = 'gtx'"
                    :class="engine === 'gtx' ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-300' : 'border-slate-200 hover:border-slate-300'"
                    class="p-4 border-2 rounded-xl text-left transition-all">
                    <p class="text-sm font-semibold text-slate-800 mb-0.5">Google Translate</p>
                    <p class="text-xs text-slate-500">Free — no API key</p>
                </button>
                <button @click="engine = 'gemini'"
                    :class="engine === 'gemini' ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-300' : 'border-slate-200 hover:border-slate-300'"
                    class="p-4 border-2 rounded-xl text-left transition-all">
                    <p class="text-sm font-semibold text-slate-800 mb-0.5">Gemini AI ✨</p>
                    <p class="text-xs text-slate-500">Fast & accurate — API key required</p>
                </button>
            </div>
            <template x-if="engine === 'gemini'">
                <div class="flex gap-2 fade-in">
                    <input x-model="geminiKey" type="password" placeholder="Gemini API Key (or GEMINI_API_KEY in .env)"
                        class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    <button @click="saveKey()" class="px-4 py-2 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-800 transition-colors">Save</button>
                    <a href="https://aistudio.google.com/app/apikey" target="_blank"
                        class="px-3 py-2 border border-slate-200 text-slate-500 text-sm rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-1">
                        Get Key
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
            </template>
        </div>
    </div>
    </template>

    {{-- Step 4 – Translate --}}
    <template x-if="lang.loaded && lang.targets.length > 0">
    <div class="bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <span class="step-badge">4</span>
            <h2 class="font-semibold text-slate-800">Translate</h2>
        </div>
        <div class="p-6">
            <template x-if="lang.translating || lang.completedLocales.length > 0">
                <div class="space-y-3 mb-5">
                    <template x-for="locale in lang.targets" :key="locale">
                        <div>
                            <div class="flex justify-between mb-1.5">
                                <span class="text-sm font-medium text-slate-700" x-text="langName(locale)"></span>
                                <span class="text-xs tabular-nums"
                                    :class="lang.progress[locale] === 100 ? 'text-emerald-600 font-semibold' : 'text-slate-400'"
                                    x-text="lang.progress[locale] === 100 ? '✓ Done' : ((lang.progress[locale] || 0) + '%')"></span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div class="h-2 rounded-full transition-all duration-500"
                                    :class="lang.progress[locale] === 100 ? 'bg-emerald-500' : 'bg-indigo-500'"
                                    :style="'width:' + (lang.progress[locale] || 0) + '%'"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="lang.status">
                <p class="text-sm text-slate-600 bg-slate-50 rounded-lg px-4 py-2.5 mb-4" x-text="lang.status"></p>
            </template>
            <template x-if="lang.txError">
                <div class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-4 py-3 mb-4 fade-in" x-text="lang.txError"></div>
            </template>

            <div class="flex gap-3">
                <button @click="langTranslate()"
                    :disabled="lang.translating || (lang.completedLocales.length === lang.targets.length && lang.targets.length > 0)"
                    class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-40 transition-colors flex items-center gap-2 shadow-sm">
                    <svg x-show="!lang.translating" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>
                    </svg>
                    <svg x-show="lang.translating" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span x-text="lang.translating ? 'Translating…' : 'Start Translation'"></span>
                </button>
                <button @click="langReset()" :disabled="lang.translating"
                    class="px-4 py-2.5 border border-slate-200 text-slate-500 text-sm rounded-lg hover:bg-slate-50 disabled:opacity-40 transition-colors">Reset</button>
            </div>
        </div>
    </div>
    </template>

    {{-- Step 5 – Save / Export --}}
    <template x-if="lang.completedLocales.length > 0">
    <div class="bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <span class="w-7 h-7 rounded-full bg-emerald-500 text-white text-xs font-bold flex items-center justify-center">✓</span>
            <h2 class="font-semibold text-slate-800">Save &amp; Export</h2>
        </div>
        <div class="p-6">
            <div class="flex flex-wrap gap-2 mb-5">
                <template x-for="locale in lang.completedLocales" :key="locale">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 border border-emerald-200 rounded-full text-sm text-emerald-700 font-medium">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span x-text="langName(locale)"></span>
                    </span>
                </template>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800 mb-4">
                <strong>Write to disk</strong> saves directly to your <code class="bg-amber-100 px-1 rounded">lang/</code> directory.
                Use <strong>Download ZIP</strong> to review files first.
            </div>

            <div class="flex flex-wrap gap-3">
                <button @click="langWrite()" :disabled="lang.saving"
                    class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    <span x-text="lang.saving ? 'Writing…' : (lang.writtenCount ? 'Written ' + lang.writtenCount.toLocaleString() + ' strings ✓' : 'Write to lang/')"></span>
                </button>

                <button @click="langDownloadZip()"
                    class="px-5 py-2.5 border border-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download ZIP
                </button>
            </div>

            <template x-if="lang.saveError">
                <p class="text-sm text-red-600 mt-3" x-text="lang.saveError"></p>
            </template>
        </div>
    </div>
    </template>

</div>{{-- /lang mode --}}
</template>

{{-- ════════════════════════════════════════════════════════════════════════════
     ▌ VOYAGER / DB TAB
═════════════════════════════════════════════════════════════════════════════ --}}
<template x-if="mode === 'voyager'">
<div class="space-y-5">

    {{-- Step 1 – Load Data --}}
    <div class="bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <span class="step-badge">1</span>
            <h2 class="font-semibold text-slate-800">Load Data</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-xl border-2 p-5 transition-colors"
                    :class="voy.dataSource === 'db' ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:border-indigo-300'">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">From Database</p>
                            <p class="text-xs text-slate-500 mt-0.5">Read directly from Voyager's <code>translations</code> table</p>
                        </div>
                    </div>
                    <button @click="voyLoadDb()" :disabled="voy.loading"
                        class="w-full px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                        <span x-text="voy.loading && voy.dataSource === 'db' ? 'Connecting…' : 'Connect & Load'"></span>
                    </button>
                </div>

                <div class="rounded-xl border-2 p-5 transition-colors"
                    :class="voy.dataSource === 'sql' ? 'border-slate-700 bg-slate-50' : 'border-slate-200 hover:border-slate-400'">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">Upload SQL Dump</p>
                            <p class="text-xs text-slate-500 mt-0.5">Upload a <code class="bg-slate-100 px-1 rounded">.sql</code> file — language auto-detected</p>
                        </div>
                    </div>
                    <label class="w-full px-4 py-2.5 bg-slate-800 text-white text-sm font-medium rounded-lg hover:bg-slate-900 transition-colors cursor-pointer flex items-center justify-center gap-2"
                        :class="voy.loading ? 'opacity-50 pointer-events-none' : ''">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <span x-text="voy.loading && voy.dataSource === 'sql' ? 'Parsing…' : 'Choose .sql file'"></span>
                        <input type="file" class="hidden" accept=".sql,.txt" @change="voyUploadSql($event)">
                    </label>
                </div>
            </div>

            <template x-if="voy.loaded">
                <div class="mt-5 bg-emerald-50 border border-emerald-200 rounded-xl p-4 fade-in">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                        <span class="text-sm font-semibold text-emerald-800">
                            Loaded <span x-text="voy.totalGroups.toLocaleString()"></span> translation groups
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(count, locale) in voy.localeStats" :key="locale">
                            <div class="flex items-center gap-1.5 px-2.5 py-1 bg-white border border-emerald-200 rounded-full text-xs">
                                <span class="font-mono font-bold text-slate-700" x-text="locale.toUpperCase()"></span>
                                <span class="text-slate-400" x-text="count.toLocaleString()"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="voy.error">
                <div class="mt-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 fade-in" x-text="voy.error"></div>
            </template>
        </div>
    </div>

    {{-- Steps 2-5 (same as before, using voy.* state) --}}
    <template x-if="voy.loaded">
    <div class="bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <span class="step-badge">2</span>
            <h2 class="font-semibold text-slate-800">Languages</h2>
        </div>
        <div class="p-6 space-y-5">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Source Language</label>
                <div class="flex items-center gap-3">
                    <select x-model="voy.sourceLang"
                        class="border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-400 min-w-[220px]">
                        <template x-for="l in langs" :key="l.code">
                            <option :value="l.code" x-text="l.flag + '  ' + l.name + ' (' + l.code + ')'"></option>
                        </template>
                    </select>
                    <span class="text-xs text-slate-400">Detected: <strong class="text-slate-600" x-text="voy.detectedLang.toUpperCase()"></strong></span>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Target Languages</label>
                <div class="flex flex-wrap gap-2">
                    <template x-for="l in langs" :key="l.code">
                        <button @click="voyToggleTarget(l.code)"
                            :disabled="l.code === voy.sourceLang"
                            :class="voy.targets.includes(l.code) ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-400'"
                            class="px-3.5 py-1.5 text-sm border rounded-full transition-all disabled:opacity-20 disabled:cursor-not-allowed">
                            <span x-text="l.flag + ' ' + l.name"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
    </template>

    <template x-if="voy.loaded">
    <div class="bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <span class="step-badge">3</span>
            <h2 class="font-semibold text-slate-800">Translation Engine</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-3 mb-4">
                <button @click="engine = 'gtx'" :class="engine==='gtx'?'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-300':'border-slate-200 hover:border-slate-300'" class="p-4 border-2 rounded-xl text-left transition-all">
                    <p class="text-sm font-semibold text-slate-800 mb-0.5">Google Translate</p>
                    <p class="text-xs text-slate-500">Free — no API key</p>
                </button>
                <button @click="engine = 'gemini'" :class="engine==='gemini'?'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-300':'border-slate-200 hover:border-slate-300'" class="p-4 border-2 rounded-xl text-left transition-all">
                    <p class="text-sm font-semibold text-slate-800 mb-0.5">Gemini AI ✨</p>
                    <p class="text-xs text-slate-500">Fast & accurate — API key required</p>
                </button>
            </div>
            <template x-if="engine === 'gemini'">
                <div class="flex gap-2 fade-in">
                    <input x-model="geminiKey" type="password" placeholder="Gemini API Key"
                        class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    <button @click="saveKey()" class="px-4 py-2 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-800 transition-colors">Save</button>
                </div>
            </template>
        </div>
    </div>
    </template>

    <template x-if="voy.loaded && voy.targets.length > 0">
    <div class="bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <span class="step-badge">4</span>
            <h2 class="font-semibold text-slate-800">Translate</h2>
        </div>
        <div class="p-6">
            <template x-if="voy.translating || voy.completedLocales.length > 0">
                <div class="space-y-3 mb-5">
                    <template x-for="locale in voy.targets" :key="locale">
                        <div>
                            <div class="flex justify-between mb-1.5">
                                <span class="text-sm font-medium text-slate-700" x-text="langName(locale)"></span>
                                <span class="text-xs tabular-nums" :class="voy.progress[locale]===100?'text-emerald-600 font-semibold':'text-slate-400'"
                                    x-text="voy.progress[locale]===100?'✓ Done':((voy.progress[locale]||0)+'%')"></span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div class="h-2 rounded-full transition-all duration-500"
                                    :class="voy.progress[locale]===100?'bg-emerald-500':'bg-indigo-500'"
                                    :style="'width:'+(voy.progress[locale]||0)+'%'"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="voy.status"><p class="text-sm text-slate-600 bg-slate-50 rounded-lg px-4 py-2.5 mb-4" x-text="voy.status"></p></template>
            <template x-if="voy.txError"><div class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-4 py-3 mb-4 fade-in" x-text="voy.txError"></div></template>

            <div class="flex gap-3">
                <button @click="voyTranslate()"
                    :disabled="voy.translating || (voy.completedLocales.length === voy.targets.length && voy.targets.length > 0)"
                    class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-40 transition-colors flex items-center gap-2 shadow-sm">
                    <svg x-show="!voy.translating" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
                    <svg x-show="voy.translating" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span x-text="voy.translating ? 'Translating…' : 'Start Translation'"></span>
                </button>
                <button @click="voyReset()" :disabled="voy.translating"
                    class="px-4 py-2.5 border border-slate-200 text-slate-500 text-sm rounded-lg hover:bg-slate-50 disabled:opacity-40 transition-colors">Reset</button>
            </div>
        </div>
    </div>
    </template>

    <template x-if="voy.completedLocales.length > 0">
    <div class="bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <span class="w-7 h-7 rounded-full bg-emerald-500 text-white text-xs font-bold flex items-center justify-center">✓</span>
            <h2 class="font-semibold text-slate-800">Save &amp; Export</h2>
        </div>
        <div class="p-6">
            <div class="flex flex-wrap gap-2 mb-5">
                <template x-for="locale in voy.completedLocales" :key="locale">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 border border-emerald-200 rounded-full text-sm text-emerald-700 font-medium">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span x-text="langName(locale)"></span>
                    </span>
                </template>
            </div>
            <div class="flex flex-wrap gap-3">
                <button @click="voySave()" :disabled="voy.saving"
                    class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    <span x-text="voy.saving ? 'Saving…' : (voy.savedCount ? 'Saved ' + voy.savedCount.toLocaleString() + ' rows ✓' : 'Save to Database')"></span>
                </button>
                <button @click="voyExportSql()" class="px-5 py-2.5 border border-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download SQL
                </button>
                <button @click="voyExportJson()" class="px-5 py-2.5 border border-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download JSON
                </button>
            </div>
        </div>
    </div>
    </template>

</div>{{-- /voyager mode --}}
</template>

</div>{{-- /container --}}

<style>
.step-badge { @apply w-7 h-7 rounded-full bg-indigo-600 text-white text-xs font-bold flex items-center justify-center; }
</style>

<script>
function app() {
    return {
        mode: 'lang', // 'lang' | 'voyager'

        // Shared
        engine:    'gtx',
        geminiKey: localStorage.getItem('vt_gemini_key') || '',

        langs: [
            { code:'tr', name:'Turkish',    flag:'🇹🇷' }, { code:'en', name:'English',    flag:'🇬🇧' },
            { code:'es', name:'Spanish',    flag:'🇪🇸' }, { code:'ru', name:'Russian',    flag:'🇷🇺' },
            { code:'de', name:'German',     flag:'🇩🇪' }, { code:'fr', name:'French',     flag:'🇫🇷' },
            { code:'ar', name:'Arabic',     flag:'🇸🇦' }, { code:'zh', name:'Chinese',    flag:'🇨🇳' },
            { code:'pt', name:'Portuguese', flag:'🇵🇹' }, { code:'it', name:'Italian',    flag:'🇮🇹' },
            { code:'ja', name:'Japanese',   flag:'🇯🇵' }, { code:'ko', name:'Korean',     flag:'🇰🇷' },
            { code:'nl', name:'Dutch',      flag:'🇳🇱' }, { code:'pl', name:'Polish',     flag:'🇵🇱' },
            { code:'uk', name:'Ukrainian',  flag:'🇺🇦' },
        ],

        // ── Lang File state ──────────────────────────────────────────────────
        lang: {
            scanning: false, scanned: false, loading: false, loaded: false,
            path: '', locales: [], stats: {}, sourceLang: 'en',
            sessionId: null, totalStrings: 0, targets: [],
            translating: false, progress: {}, completedLocales: [],
            status: '', txError: '',
            saving: false, writtenCount: 0, saveError: '', error: '',
        },

        // ── Voyager state ────────────────────────────────────────────────────
        voy: {
            loading: false, loaded: false, dataSource: null,
            sessionId: null, totalGroups: 0, localeStats: {},
            detectedLang: 'en', sourceLang: 'en', targets: [],
            translating: false, progress: {}, completedLocales: [],
            status: '', txError: '',
            saving: false, savedCount: 0, error: '',
        },

        switchMode(m) {
            this.mode = m;
        },

        langName(code) {
            const l = this.langs.find(x => x.code === code);
            return l ? l.flag + ' ' + l.name : code.toUpperCase();
        },

        saveKey() { localStorage.setItem('vt_gemini_key', this.geminiKey); },

        // ═══ LANG FILE METHODS ═══════════════════════════════════════════════

        async langScan() {
            this.lang.scanning = true;
            this.lang.error    = '';
            try {
                const r = await this.get(window.VTR.scanLang);
                this.lang.path    = r.path;
                this.lang.locales = r.locales;
                this.lang.stats   = r.stats;
                this.lang.scanned = true;
                // Auto-select most-populated locale as source
                const top = Object.entries(r.stats).sort((a,b) => b[1]-a[1])[0];
                if (top) this.lang.sourceLang = top[0];
            } catch(e) {
                this.lang.error = 'Scan error: ' + e.message;
            } finally {
                this.lang.scanning = false;
            }
        },

        async langLoad() {
            this.lang.loading = true;
            this.lang.error   = '';
            try {
                const r = await this.post(window.VTR.loadLang, { source_lang: this.lang.sourceLang });
                if (!r.success) throw new Error(r.error);
                this.lang.sessionId    = r.id;
                this.lang.totalStrings = r.total;
                this.lang.loaded       = true;
                // Pre-select all other locales as targets
                this.lang.targets = this.lang.locales.filter(l => l !== this.lang.sourceLang);
            } catch(e) {
                this.lang.error = 'Load error: ' + e.message;
            } finally {
                this.lang.loading = false;
            }
        },

        langToggleTarget(code) {
            if (this.lang.targets.includes(code)) {
                this.lang.targets = this.lang.targets.filter(l => l !== code);
            } else {
                this.lang.targets = [...this.lang.targets, code];
            }
        },

        async langTranslate() {
            if (!this.lang.sessionId || this.lang.targets.length === 0) return;
            this.lang.translating      = true;
            this.lang.completedLocales = [];
            this.lang.progress         = {};
            this.lang.txError          = '';

            const batchSize = this.engine === 'gemini' ? 60 : 20;

            for (const locale of this.lang.targets) {
                this.lang.status  = 'Translating → ' + this.langName(locale) + '…';
                this.lang.progress = { ...this.lang.progress, [locale]: 0 };

                let idx = 0, done = false, retries = 0;
                while (!done) {
                    try {
                        const r = await this.post(window.VTR.translateLangBatch, {
                            id: this.lang.sessionId, locale,
                            batch_index: idx, batch_size: batchSize,
                            engine: this.engine, gemini_key: this.geminiKey || null,
                        });
                        if (!r.success) throw new Error(r.error);
                        if (r.done) {
                            done = true;
                            this.lang.progress = { ...this.lang.progress, [locale]: 100 };
                        } else {
                            this.lang.progress = { ...this.lang.progress, [locale]: r.progress };
                            idx++; retries = 0;
                        }
                    } catch(e) {
                        if (++retries >= 3) { this.lang.txError = locale + ': ' + e.message; done = true; }
                        else await new Promise(r => setTimeout(r, 2000 * retries));
                    }
                }
                if (this.lang.progress[locale] === 100)
                    this.lang.completedLocales = [...this.lang.completedLocales, locale];
            }

            this.lang.translating = false;
            this.lang.status = this.lang.txError ? '' : '✓ All done!';
        },

        async langWrite() {
            this.lang.saving    = true;
            this.lang.saveError = '';
            try {
                const r = await this.post(window.VTR.writeLang, {
                    id: this.lang.sessionId,
                    locales: this.lang.completedLocales,
                });
                if (!r.success) throw new Error(r.error);
                this.lang.writtenCount = r.written;
            } catch(e) {
                this.lang.saveError = 'Write error: ' + e.message;
            } finally {
                this.lang.saving = false;
            }
        },

        langDownloadZip() {
            const loc = this.lang.completedLocales.join(',');
            window.location.href = window.VTR.exportLangZip + '?id=' + this.lang.sessionId + '&locales=' + loc;
        },

        langReset() {
            Object.assign(this.lang, {
                loaded: false, sessionId: null, totalStrings: 0, targets: [],
                translating: false, progress: {}, completedLocales: [],
                status: '', txError: '', saving: false, writtenCount: 0, saveError: '',
            });
        },

        // ═══ VOYAGER METHODS ═════════════════════════════════════════════════

        async voyLoadDb() {
            this.voy.loading    = true;
            this.voy.dataSource = 'db';
            this.voy.error      = '';
            try {
                const r = await this.post(window.VTR.loadDb, {});
                this._applyVoyResult(r);
            } catch(e) {
                this.voy.error = 'Error: ' + e.message;
            } finally { this.voy.loading = false; }
        },

        async voyUploadSql(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.voy.loading    = true;
            this.voy.dataSource = 'sql';
            this.voy.error      = '';
            try {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                const r = await (await fetch(window.VTR.uploadSql, { method:'POST', body:fd })).json();
                this._applyVoyResult(r);
            } catch(e) {
                this.voy.error = 'Upload error: ' + e.message;
            } finally { this.voy.loading = false; }
        },

        _applyVoyResult(r) {
            if (!r.success) throw new Error(r.error || 'Unknown error');
            this.voy.sessionId    = r.id;
            this.voy.totalGroups  = r.total;
            this.voy.localeStats  = r.locale_stats;
            this.voy.detectedLang = r.detected_lang;
            this.voy.sourceLang   = r.detected_lang;
            this.voy.loaded       = true;
            this.voy.targets      = Object.keys(r.locale_stats).filter(l => l !== r.detected_lang);
        },

        voyToggleTarget(code) {
            if (this.voy.targets.includes(code)) this.voy.targets = this.voy.targets.filter(l=>l!==code);
            else this.voy.targets = [...this.voy.targets, code];
        },

        async voyTranslate() {
            if (!this.voy.sessionId || this.voy.targets.length === 0) return;
            this.voy.translating      = true;
            this.voy.completedLocales = [];
            this.voy.progress         = {};
            this.voy.txError          = '';

            const batchSize = this.engine === 'gemini' ? 40 : 15;

            for (const locale of this.voy.targets) {
                this.voy.status   = 'Translating → ' + this.langName(locale) + '…';
                this.voy.progress = { ...this.voy.progress, [locale]: 0 };

                let idx = 0, done = false, retries = 0;
                while (!done) {
                    try {
                        const r = await this.post(window.VTR.translateBatch, {
                            id: this.voy.sessionId, source_lang: this.voy.sourceLang, locale,
                            batch_index: idx, batch_size: batchSize,
                            engine: this.engine, gemini_key: this.geminiKey || null,
                        });
                        if (!r.success) throw new Error(r.error);
                        if (r.done) {
                            done = true;
                            this.voy.progress = { ...this.voy.progress, [locale]: 100 };
                        } else {
                            this.voy.progress = { ...this.voy.progress, [locale]: r.progress };
                            idx++; retries = 0;
                        }
                    } catch(e) {
                        if (++retries >= 3) { this.voy.txError = locale + ': ' + e.message; done = true; }
                        else await new Promise(r => setTimeout(r, 2000 * retries));
                    }
                }
                if (this.voy.progress[locale] === 100)
                    this.voy.completedLocales = [...this.voy.completedLocales, locale];
            }
            this.voy.translating = false;
            this.voy.status = this.voy.txError ? '' : '✓ All done!';
        },

        async voySave() {
            this.voy.saving = true;
            try {
                const r = await this.post(window.VTR.save, { id: this.voy.sessionId, locales: this.voy.completedLocales });
                if (!r.success) throw new Error(r.error);
                this.voy.savedCount = r.saved;
            } catch(e) {
                this.voy.txError = 'Save error: ' + e.message;
            } finally { this.voy.saving = false; }
        },

        voyExportSql()  { window.location.href = window.VTR.exportSql  + '?id=' + this.voy.sessionId + '&locales=' + this.voy.completedLocales.join(','); },
        voyExportJson() { window.location.href = window.VTR.exportJson + '?id=' + this.voy.sessionId + '&locales=' + this.voy.completedLocales.join(','); },

        voyReset() {
            Object.assign(this.voy, {
                loaded: false, sessionId: null, totalGroups: 0, localeStats: {},
                targets: [], completedLocales: [], progress: {},
                status: '', txError: '', saving: false, savedCount: 0,
            });
        },

        // ═══ HTTP HELPERS ════════════════════════════════════════════════════

        async get(url) {
            const r = await fetch(url, { headers: { Accept: 'application/json' } });
            return r.json();
        },

        async post(url, data) {
            const r = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify(data),
            });
            const json = await r.json();
            if (r.status >= 500) throw new Error(json.error || 'Server error');
            return json;
        },
    };
}
</script>
</body>
</html>
