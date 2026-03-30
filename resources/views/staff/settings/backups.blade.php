@extends('requests::staff.settings._layout')
@section('title', 'Backups')
@section('settings-content')

@php
    // Reusable inline Heroicon outline SVGs (24px, stroke-width 1.5)
    $icon = [
        'download'  => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>',
        'upload'    => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>',
        'restore'   => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>',
        'server'    => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z"/></svg>',
        'db'        => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>',
        'config'    => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'archive'   => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>',
        'warning'   => '<svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>',
        'trash'     => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>',
    'question'  => '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>',
    ];

    /**
     * Render a question-mark icon that shows a tooltip on hover listing backup contents.
     * $items: array of strings (each becomes a bullet point).
     */
    $infoTooltip = function (array $items, string $direction = 'up') use (&$infoTooltip): string {
        $bullets = implode('', array_map(
            fn ($item) => '<li class="flex items-start gap-1.5"><span class="mt-0.5 shrink-0 text-blue-400">•</span><span>' . e($item) . '</span></li>',
            $items
        ));
        $iconSvg = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>';
        if ($direction === 'down') {
            $bubblePos = 'top-full left-1/2 -translate-x-1/2 mt-2';
            $caret     = '<span class="absolute bottom-full left-1/2 -translate-x-1/2 border-4 border-transparent border-b-gray-900"></span>';
        } else {
            $bubblePos = 'bottom-full left-1/2 -translate-x-1/2 mb-2';
            $caret     = '<span class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></span>';
        }

        return '<span class="relative inline-flex items-center" x-data="{ show: false }" @mouseenter="show = true" @mouseleave="show = false">'
            . '<button type="button" class="text-gray-400 hover:text-blue-500 transition-colors focus:outline-none">'
            . $iconSvg
            . '</button>'
            . '<span x-show="show" x-cloak x-transition.opacity'
            . '      class="absolute ' . $bubblePos . ' z-50 w-64 rounded-lg bg-gray-900 px-3 py-2.5 text-xs text-gray-100 shadow-lg pointer-events-none">'
            . '<ul class="space-y-1">' . $bullets . '</ul>'
            . $caret
            . '</span>'
            . '</span>';
    };
@endphp

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Backups</h1>
</div>

@if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- Section 1 — Configuration                                                --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}

<div class="mb-6">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Configuration</h2>
    <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">

        {{-- Export config --}}
        <div class="p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 flex items-center gap-1.5">
                Export
                {!! $infoTooltip([
                    'Settings — all application configuration key/value pairs',
                    'Request statuses — name, color, icon, action label, SFP/ILL scope, terminal state, notifications, sort order',
                    'Forms — SFP and ILL form definitions',
                    'Fields — all form field definitions (label, type, scope, required, conditional logic) with options',
                    'Form field config — per-form field visibility, order, required, and step overrides',
                    'Selector groups — name, description, and linked field options',
                    'Catalog format labels',
                    'Staff routing templates — per-group email subject and body',
                    'Patron status templates — subject, body, linked statuses and field options',
                ], 'down') !!}
            </h3>
            <p class="text-sm text-gray-500 mb-4">
                Download a JSON snapshot of all configuration — statuses, material types, audiences,
                selector groups, catalog format labels, and settings. Request data is not included.
            </p>
            <form method="POST" action="{{ route('request.staff.backups.config-export') }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 font-medium">
                    {!! $icon['download'] !!} Export Configuration
                </button>
            </form>
        </div>

        {{-- Import config --}}
        <div class="p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Import</h3>
            <p class="text-sm text-gray-500 mb-4">
                Upload a previously exported configuration file. Existing records are
                <strong>updated</strong> (matched by slug or name); new records are inserted.
                Nothing is deleted during import.
            </p>
            <form method="POST"
                  action="{{ route('request.staff.backups.config-import') }}"
                  enctype="multipart/form-data">
                @csrf
                <div class="space-y-3">
                    <input type="file"
                           name="backup_file"
                           accept=".json,application/json"
                           required
                           class="block text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 font-medium">
                        {!! $icon['upload'] !!} Import Configuration
                    </button>
                </div>
                @error('backup_file')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </form>
        </div>

    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- Section 2 — Database & Storage                                           --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}

<div class="mb-6">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Database &amp; Storage</h2>
    <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">

        {{-- DB Export --}}
        <div class="p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 flex items-center gap-1.5">
                Export Database
                {!! $infoTooltip([
                    'Full SQL dump of all database tables',
                    'Requests, patrons, and materials',
                    'Request history and status transitions',
                    'Settings, request statuses, and field definitions',
                    'Staff users and assignments',
                    'Notification templates and pivot tables',
                    'Can be used to fully restore the database',
                ]) !!}
            </h3>
            <p class="text-sm text-gray-500 mb-4">
                Download a full SQL dump of the database. Includes all tables and data —
                requests, patrons, titles, and configuration.
            </p>
            <form method="POST" action="{{ route('request.staff.backups.db-export') }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 font-medium">
                    {!! $icon['download'] !!} Download SQL Dump
                </button>
            </form>
        </div>

        {{-- DB Restore --}}
        <div class="p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Import Database</h3>
            <p class="text-sm text-gray-500 mb-4">
                Upload a <code class="bg-gray-100 px-1 py-0.5 rounded text-xs font-mono">.sql</code> file
                to restore the database.
                <strong class="text-orange-600">This will overwrite existing data.</strong>
                Export a backup first.
            </p>
            <form method="POST"
                  action="{{ route('request.staff.backups.db-import') }}"
                  enctype="multipart/form-data"
                  onsubmit="return confirm('This will overwrite the current database with the uploaded file. Are you sure?')">
                @csrf
                <div class="space-y-3">
                    <input type="file"
                           name="sql_file"
                           accept=".sql,text/plain"
                           required
                           class="block text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm rounded font-medium">
                        {!! $icon['restore'] !!} Restore Database
                    </button>
                </div>
                @error('sql_file')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </form>
        </div>

        {{-- Storage Export --}}
        <div class="p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 flex items-center gap-1.5">
                Export Storage
                {!! $infoTooltip([
                    'Zip archive of the storage/app directory',
                    'Uploaded attachments and stored assets',
                    'Must be extracted manually to restore',
                ]) !!}
            </h3>
            <p class="text-sm text-gray-500 mb-4">
                Download a zip archive of <code class="bg-gray-100 px-1 py-0.5 rounded text-xs font-mono">storage/app</code>.
                Useful for backing up uploaded files and other stored assets.
            </p>
            <form method="POST" action="{{ route('request.staff.backups.storage-export') }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 font-medium">
                    {!! $icon['download'] !!} Download Storage Zip
                </button>
            </form>
        </div>

        {{-- Save to Server --}}
        <div class="p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Save to Server</h3>
            <p class="text-sm text-gray-500 mb-4">
                Write a backup directly to
                <code class="bg-gray-100 px-1 py-0.5 rounded text-xs font-mono">storage/app/requests-backups</code>
                on the server. Files saved here appear in the Server Backups section below and
                survive browser sessions — no download required.
            </p>
            @error('save')
                <p class="mb-3 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <form method="POST" action="{{ route('request.staff.backups.server-save') }}">
                @csrf
                <div class="flex flex-wrap items-center gap-4 mb-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="types[]" value="config" checked
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Configuration (JSON)
                        {!! $infoTooltip([
                            'Settings and request statuses',
                            'Forms, fields, form field config',
                            'Selector groups, catalog format labels',
                            'Staff routing and patron status templates',
                        ]) !!}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="types[]" value="db" checked
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Database (JSON)
                        {!! $infoTooltip([
                            'Full JSON dump of all tables — database agnostic',
                            'Requests, patrons, materials, history',
                            'All configuration and template data',
                        ]) !!}
                    </label>
                </div>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 font-medium">
                    {!! $icon['server'] !!} Save to Server Now
                </button>
            </form>
        </div>

        {{-- Wipe Everything --}}
        <div class="p-5" style="background: rgba(254,242,242,.4)">
            <h3 class="text-sm font-semibold text-red-700 mb-1">Wipe Everything</h3>
            <p class="text-sm text-gray-500 mb-1">
                Truncates every table in the database — all requests, patrons, titles,
                configuration, and settings.
            </p>
            <p class="flex items-center gap-1.5 text-sm font-medium text-red-700 mb-4">
                {!! $icon['warning'] !!} This cannot be undone. Export a database backup before proceeding.
            </p>
            <form method="POST"
                  action="{{ route('request.staff.backups.wipe') }}"
                  onsubmit="return confirm('This will permanently delete ALL data in the database. This cannot be undone. Proceed?')">
                @csrf
                <div class="flex items-center gap-3">
                    <input type="text"
                           name="confirm_wipe"
                           placeholder="Type WIPE to confirm"
                           autocomplete="off"
                           class="text-sm border border-red-300 rounded px-3 py-1.5 w-52 focus:outline-none focus:ring-1 focus:ring-red-400">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700 font-medium">
                        {!! $icon['trash'] !!} Wipe Everything
                    </button>
                </div>
                @error('confirm_wipe')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </form>
        </div>

    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- Section 3 — Server Backups                                               --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}

@php
    $perPage = 5;
    $hasAny  = collect($serverFiles)->flatten(1)->isNotEmpty();
    $groups  = [
        ['key' => 'db',      'label' => 'Database Backups',      'icon' => 'db',      'isDb' => true],
        ['key' => 'config',  'label' => 'Configuration Backups', 'icon' => 'config',  'isDb' => false],
        ['key' => 'storage', 'label' => 'Storage Backups',       'icon' => 'archive', 'isDb' => false],
    ];
@endphp

<div class="mb-6">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Server Backups</h2>
    <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">

        @if($errors->has('filename'))
            <div class="px-5 py-3 bg-red-50 border-b border-red-200 text-red-800 text-sm">
                {{ $errors->first('filename') }}
            </div>
        @endif

        @if(! $hasAny)
        <div class="p-5">
            <p class="text-sm text-gray-500">
                No backup files found in
                <code class="bg-gray-100 px-1 py-0.5 rounded text-xs font-mono">storage/app/requests-backups</code>.
                Use <strong>Save to Server Now</strong> above or run
                <code class="bg-gray-100 px-1 py-0.5 rounded text-xs font-mono">php artisan requests:backup --config --db</code>
                to create server-side backups.
            </p>
        </div>
        @else
            @foreach($groups as $group)
            @php $files = $serverFiles[$group['key']] ?? []; $total = count($files); @endphp
            <div class="p-5"
                 x-data="{ page: 1, perPage: {{ $perPage }}, total: {{ $total }} }"
                 x-init="">
                <h3 class="flex items-center gap-1.5 text-sm font-semibold text-gray-700 mb-3">
                    {!! $icon[$group['icon']] !!} {{ $group['label'] }}
                    <span class="ml-1 text-xs font-normal text-gray-400">({{ $total }})</span>
                </h3>

                @if($total === 0)
                <p class="text-sm text-gray-500">
                    @if($group['key'] === 'storage')
                        No storage zip backups in the backup directory yet. They appear here when you enable
                        <strong>Storage (Zip)</strong> on the schedule, run <code class="bg-gray-100 px-1 py-0.5 rounded text-xs font-mono">php artisan requests:backup --storage</code>,
                        or use <strong>Download Storage Zip</strong> and save the file to this server.
                    @else
                        No {{ strtolower($group['label']) }} in the scanned backup directories yet.
                    @endif
                </p>
                @else
                <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200 overflow-hidden">
                    @foreach($files as $i => $file)
                    @php $sizeLabel = $group['key'] === 'storage'
                        ? number_format($file['size'] / 1024 / 1024, 1) . ' MB'
                        : number_format($file['size'] / 1024, 0) . ' KB'; @endphp
                    <li class="flex items-center justify-between px-4 py-3 bg-white hover:bg-gray-50 text-sm"
                        x-show="{{ $i }} >= (page - 1) * perPage && {{ $i }} < page * perPage">
                        {{-- File info --}}
                        <div class="min-w-0 mr-4">
                            <p class="font-mono text-xs text-gray-800 truncate">{{ $file['name'] }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ \Illuminate\Support\Carbon::createFromTimestamp($file['modified'])->format('M j, Y g:i A') }}
                                &middot; {{ $sizeLabel }}
                            </p>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center gap-2 shrink-0">

                            {{-- Download --}}
                            <a href="{{ route('request.staff.backups.server-download', ['filename' => $file['name']]) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded bg-gray-100 text-gray-700 hover:bg-gray-200">
                                {!! $icon['download'] !!} Download
                            </a>

                            @if($group['key'] !== 'storage')
                            {{-- Restore --}}
                            <form method="POST"
                                  action="{{ route('request.staff.backups.server-restore') }}"
                                  class="inline"
                                  onsubmit="return confirm('Restore from {{ $file['name'] }}?\n\n{{ $group['isDb'] ? 'This will overwrite the current database.' : 'Existing config will be updated.' }}\n\nContinue?')">
                                @csrf
                                <input type="hidden" name="filename" value="{{ $file['name'] }}">
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded
                                            {{ $group['isDb']
                                                ? 'bg-orange-100 text-orange-700 hover:bg-orange-200'
                                                : 'bg-blue-100 text-blue-700 hover:bg-blue-200' }}">
                                    {!! $icon['restore'] !!} Restore
                                </button>
                            </form>
                            @endif
                            {{-- Delete --}}
                            <form method="POST"
                                  action="{{ route('request.staff.backups.server-delete') }}"
                                  class="inline"
                                  onsubmit="return confirm('Delete {{ $file['name'] }}? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="filename" value="{{ $file['name'] }}">
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded bg-red-50 text-red-700 hover:bg-red-100">
                                    {!! $icon['trash'] !!} Delete
                                </button>
                            </form>
                        </div>
                    </li>
                    @endforeach
                </ul>

                {{-- Pagination --}}
                @if($total > $perPage)
                <div class="flex items-center justify-between mt-3">
                    <p class="text-xs text-gray-400">
                        Showing <span x-text="(page - 1) * perPage + 1"></span>–<span x-text="Math.min(page * perPage, total)"></span> of {{ $total }}
                    </p>
                    <div class="flex items-center gap-1">
                        <button @click="page = Math.max(1, page - 1)"
                                :disabled="page === 1"
                                class="px-2.5 py-1 text-xs rounded border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                            &lsaquo; Prev
                        </button>
                        <span class="px-2 text-xs text-gray-500">
                            <span x-text="page"></span> / <span x-text="Math.ceil(total / perPage)"></span>
                        </span>
                        <button @click="page = Math.min(Math.ceil(total / perPage), page + 1)"
                                :disabled="page >= Math.ceil(total / perPage)"
                                class="px-2.5 py-1 text-xs rounded border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                            Next &rsaquo;
                        </button>
                    </div>
                </div>
                @endif

                @if($group['key'] === 'storage')
                <p class="mt-2 text-xs text-gray-400">
                    Storage backups must be restored manually by extracting to
                    <code class="bg-gray-100 px-1 rounded font-mono">storage/app</code>.
                </p>
                @endif

                @endif
            </div>
            @endforeach
        @endif

    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- Section 4 — Backup Schedule & Retention                                  --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}

<div class="mb-6">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Schedule &amp; Retention</h2>
    <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">

        {{-- Retention setting --}}
        <div class="p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Backup Retention</h3>
            <p class="text-sm text-gray-500 mb-4">
                Server-side backup files older than this are removed when pruning runs.
                Pruning can be triggered manually below or scheduled via the queue worker.
            </p>
            <form method="POST" action="{{ route('request.staff.backups.retention') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Keep backups for</label>
                    <div class="flex items-center gap-2">
                        <input type="number"
                               name="retention_days"
                               value="{{ $retentionDays }}"
                               min="1" max="3650"
                               class="w-24 text-sm border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-400">
                        <span class="text-sm text-gray-500">days</span>
                    </div>
                    @error('retention_days')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded font-medium">
                    {!! $icon['config'] !!} Save Retention
                </button>
            </form>

            {{-- Prune now --}}
            <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-700 font-medium">Prune Now</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Dispatch a queue job that immediately deletes backups older than {{ $retentionDays }} day{{ $retentionDays === 1 ? '' : 's' }}.
                        Requires a running queue worker (<code class="bg-gray-100 px-1 rounded font-mono text-xs">php artisan queue:work</code>).
                    </p>
                </div>
                <form method="POST"
                      action="{{ route('request.staff.backups.prune') }}"
                      onsubmit="return confirm('Dispatch a pruning job to delete backups older than {{ $retentionDays }} days?')">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded font-medium whitespace-nowrap">
                        {!! $icon['trash'] !!} Prune Old Backups
                    </button>
                </form>
            </div>
        </div>

        {{-- Automated backup schedule (Laravel scheduler — see package docs/scheduler.md) --}}
        <div class="p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Automated backup schedule</h3>
            <p class="text-sm text-gray-500 mb-5">
                When enabled, the package registers a Laravel scheduler task from these settings.
                Your deployment must run <code class="bg-gray-100 px-1 py-0.5 rounded text-xs font-mono">php artisan schedule:run</code>
                every minute (e.g. a scheduler container). You do not need a duplicate entry in
                <code class="bg-gray-100 px-1 py-0.5 rounded text-xs font-mono">routes/console.php</code> unless you prefer to manage timing there instead.
            </p>

            <form method="POST" action="{{ route('request.staff.backups.schedule') }}" id="backup-schedule-form" class="space-y-4">
                @csrf
                <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                    <input type="hidden" name="backup_schedule_enabled" value="0">
                    <input type="checkbox" name="backup_schedule_enabled" value="1"
                           class="rounded border-gray-300"
                           @checked(old('backup_schedule_enabled', $backupScheduleEnabled ? '1' : '0') === '1')>
                    Enable scheduled backups
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Frequency</label>
                        <select name="schedule_preset" id="schedule_preset"
                                class="w-full text-sm border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-400">
                            <option value="daily_2" @selected(old('schedule_preset', $backupSchedulePreset) === 'daily_2')>Daily at 2:00 AM</option>
                            <option value="weekly_sun_2" @selected(old('schedule_preset', $backupSchedulePreset) === 'weekly_sun_2')>Weekly — Sunday 2:00 AM</option>
                            <option value="monthly_1_2" @selected(old('schedule_preset', $backupSchedulePreset) === 'monthly_1_2')>Monthly — 1st at 2:00 AM</option>
                            <option value="custom" @selected(old('schedule_preset', $backupSchedulePreset) === 'custom')>Custom cron…</option>
                        </select>
                    </div>
                    <div id="cron-custom-wrap" class="@if(old('schedule_preset', $backupSchedulePreset) !== 'custom') hidden @endif">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Cron expression (five fields)</label>
                        <input type="text" name="cron_custom" id="cron_custom"
                               value="{{ old('cron_custom', $backupScheduleCron) }}"
                               placeholder="0 2 * * *"
                               class="w-full text-sm border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-400 font-mono">
                        @error('cron_custom')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-2">Include in each run</label>
                    <div class="flex flex-wrap gap-5 text-sm text-gray-700">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="hidden" name="include_config" value="0">
                            <input type="checkbox" name="include_config" value="1" class="rounded border-gray-300"
                                   @checked(old('include_config', $backupScheduleIncludeConfig ? '1' : '0') === '1')>
                            Configuration (JSON)
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="hidden" name="include_db" value="0">
                            <input type="checkbox" name="include_db" value="1" class="rounded border-gray-300"
                                   @checked(old('include_db', $backupScheduleIncludeDb ? '1' : '0') === '1')>
                            Database (JSON dump)
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="hidden" name="include_storage" value="0">
                            <input type="checkbox" name="include_storage" value="1" class="rounded border-gray-300"
                                   @checked(old('include_storage', $backupScheduleIncludeStorage ? '1' : '0') === '1')>
                            Storage (Zip)
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="hidden" name="include_prune" value="0">
                            <input type="checkbox" name="include_prune" value="1" class="rounded border-gray-300"
                                   @checked(old('include_prune', $backupSchedulePrune ? '1' : '0') === '1')>
                            Prune old backups after
                        </label>
                    </div>
                    @error('include_config')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Output directory <span class="text-gray-400 font-normal">(optional — default is storage/app/requests-backups)</span></label>
                    <input type="text" name="backup_path" value="{{ old('backup_path', $backupSchedulePath) }}"
                           placeholder="{{ storage_path('app/requests-backups') }}"
                           class="w-full text-sm border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-400 font-mono">
                    @error('backup_path')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded font-medium">
                    {!! $icon['config'] !!} Save schedule
                </button>
            </form>

            <div class="border-t border-gray-100 pt-4 mt-5">
                <h3 class="text-xs font-semibold text-gray-600 mb-2">Reference: manual cron (optional)</h3>
                <p class="text-xs text-gray-500 mb-2">If you do not use the Laravel scheduler, you can still call Artisan from system cron, e.g.:</p>
                <pre class="bg-gray-900 text-green-400 rounded-lg px-4 py-3 text-xs overflow-x-auto leading-relaxed">0 2 * * * www-data php /path/to/artisan requests:backup --config --db --prune &gt;&gt; /var/log/requests-backup.log 2&gt;&amp;1</pre>
                <p class="text-xs text-amber-800 mt-2">
                    Do not use <code class="bg-amber-100 px-1 rounded font-mono">--all</code> here unless you want a full <code class="bg-amber-100 px-1 rounded font-mono">storage/app</code> zip every run
                    (large). Add <code class="bg-amber-100 px-1 rounded font-mono">--storage</code> only when you intend that. The Laravel scheduler uses your checkboxes above, not this cron line.
                </p>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    var preset = document.getElementById('schedule_preset');
    var wrap = document.getElementById('cron-custom-wrap');
    if (preset && wrap) {
        function sync() {
            wrap.classList.toggle('hidden', preset.value !== 'custom');
        }
        preset.addEventListener('change', sync);
        sync();
    }
})();
</script>

@endsection
