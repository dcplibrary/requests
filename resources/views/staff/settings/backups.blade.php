@extends('sfp::staff.settings._layout')
@section('title', 'Backups')
@section('settings-content')

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
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Export</h3>
            <p class="text-sm text-gray-500 mb-4">
                Download a JSON snapshot of all configuration — statuses, material types, audiences,
                selector groups, catalog format labels, and settings. Request data is not included.
            </p>
            <form method="POST" action="{{ route('sfp.staff.backups.config-export') }}">
                @csrf
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 font-medium">
                    ↓ Export Configuration
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
                  action="{{ route('sfp.staff.backups.config-import') }}"
                  enctype="multipart/form-data">
                @csrf
                <div class="flex items-center gap-3">
                    <input type="file"
                           name="backup_file"
                           accept=".json,application/json"
                           required
                           class="text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 font-medium whitespace-nowrap">
                        ↑ Import Configuration
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
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Export Database</h3>
            <p class="text-sm text-gray-500 mb-4">
                Download a full SQL dump of the database. Includes all tables and data —
                requests, patrons, titles, and configuration.
            </p>
            <form method="POST" action="{{ route('sfp.staff.backups.db-export') }}">
                @csrf
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 font-medium">
                    ↓ Download SQL Dump
                </button>
            </form>
        </div>

        {{-- Storage Export --}}
        <div class="p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Export Storage</h3>
            <p class="text-sm text-gray-500 mb-4">
                Download a zip archive of <code class="bg-gray-100 px-1 py-0.5 rounded text-xs font-mono">storage/app</code>.
                Useful for backing up uploaded files and other stored assets.
            </p>
            <form method="POST" action="{{ route('sfp.staff.backups.storage-export') }}">
                @csrf
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 font-medium">
                    ↓ Download Storage Zip
                </button>
            </form>
        </div>

        {{-- DB Restore --}}
        <div class="p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Restore Database</h3>
            <p class="text-sm text-gray-500 mb-4">
                Upload a <code class="bg-gray-100 px-1 py-0.5 rounded text-xs font-mono">.sql</code> file
                to restore the database.
                <strong class="text-orange-600">This will overwrite existing data.</strong>
                Export a backup first.
            </p>
            <form method="POST"
                  action="{{ route('sfp.staff.backups.db-import') }}"
                  enctype="multipart/form-data"
                  onsubmit="return confirm('This will overwrite the current database with the uploaded file. Are you sure?')">
                @csrf
                <div class="flex items-center gap-3">
                    <input type="file"
                           name="sql_file"
                           accept=".sql,text/plain"
                           required
                           class="text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                    <button type="submit"
                            class="px-4 py-2 bg-orange-500 text-white text-sm rounded hover:bg-orange-600 font-medium whitespace-nowrap">
                        ↑ Restore Database
                    </button>
                </div>
                @error('sql_file')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </form>
        </div>

        {{-- Wipe Everything --}}
        <div class="p-5" style="background: rgba(254,242,242,.4)">
            <h3 class="text-sm font-semibold text-red-700 mb-1">Wipe Everything</h3>
            <p class="text-sm text-gray-500 mb-1">
                Truncates every table in the database — all requests, patrons, titles,
                configuration, and settings.
            </p>
            <p class="text-sm font-medium text-red-700 mb-4">
                ⚠ This cannot be undone. Export a database backup before proceeding.
            </p>
            <form method="POST"
                  action="{{ route('sfp.staff.backups.wipe') }}"
                  onsubmit="return confirm('This will permanently delete ALL data in the database. This cannot be undone. Proceed?')">
                @csrf
                <div class="flex items-center gap-3">
                    <input type="text"
                           name="confirm_wipe"
                           placeholder="Type WIPE to confirm"
                           autocomplete="off"
                           class="text-sm border border-red-300 rounded px-3 py-1.5 w-52 focus:outline-none focus:ring-1 focus:ring-red-400">
                    <button type="submit"
                            class="px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700 font-medium">
                        Wipe Everything
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
{{-- Section 3 — Backup Schedule                                              --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}

<div class="mb-6">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Backup Schedule</h2>
    <div class="bg-white rounded-lg border border-gray-200 p-5">

        <p class="text-sm text-gray-500 mb-5">
            Use <code class="bg-gray-100 px-1 py-0.5 rounded text-xs font-mono">php artisan sfp:backup</code>
            on a schedule via Docker cron or the Laravel scheduler. Configure the options below to
            generate your cron line.
        </p>

        <div id="schedule-builder" class="space-y-4 mb-5">

            {{-- Frequency --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Frequency</label>
                    <select id="sched-frequency"
                            class="w-full text-sm border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-400">
                        <option value="0 2 * * *">Daily at 2:00 AM</option>
                        <option value="0 2 * * 0">Weekly — Sunday at 2:00 AM</option>
                        <option value="0 2 1 * *">Monthly — 1st at 2:00 AM</option>
                        <option value="custom">Custom cron expression…</option>
                    </select>
                </div>
                <div id="sched-custom-wrap" class="hidden">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Custom expression</label>
                    <input type="text" id="sched-custom"
                           placeholder="0 2 * * *"
                           class="w-full text-sm border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-400">
                </div>
            </div>

            {{-- Include --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-2">Include in backup</label>
                <div class="flex flex-wrap gap-5 text-sm text-gray-700">
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" id="sched-config" checked class="rounded border-gray-300"> Configuration (JSON)
                    </label>
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" id="sched-db" checked class="rounded border-gray-300"> Database (SQL)
                    </label>
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" id="sched-storage" class="rounded border-gray-300"> Storage (Zip)
                    </label>
                </div>
            </div>

            {{-- Output path --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Backup output path <span class="text-gray-400 font-normal">(inside container)</span></label>
                <input type="text" id="sched-path"
                       value="/var/backups/sfp"
                       class="w-full text-sm border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-400">
            </div>

            {{-- App path --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Laravel app path <span class="text-gray-400 font-normal">(inside container)</span></label>
                <input type="text" id="sched-app"
                       value="/var/www/html"
                       class="w-full text-sm border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-400">
            </div>

            {{-- Generated cron line --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Generated cron line</label>
                <div id="sched-output"
                     class="bg-gray-900 text-green-400 rounded-lg px-4 py-3 font-mono text-xs overflow-x-auto whitespace-nowrap select-all cursor-text">
                    0 2 * * * www-data php /var/www/html/artisan sfp:backup --config --db --path=/var/backups/sfp >> /var/log/sfp-backup.log 2>&1
                </div>
                <p class="mt-1.5 text-xs text-gray-400">Click to select all. Add this to your container's crontab.</p>
            </div>

        </div>

        {{-- Docker example --}}
        <div class="border-t border-gray-100 pt-4">
            <h3 class="text-xs font-semibold text-gray-600 mb-2">Docker setup</h3>
            <pre class="bg-gray-900 text-green-400 rounded-lg px-4 py-3 text-xs overflow-x-auto leading-relaxed"># Option A — cron inside the container
RUN apt-get install -y cron
COPY crontab /etc/cron.d/sfp-backup
RUN chmod 0644 /etc/cron.d/sfp-backup &amp;&amp; crontab /etc/cron.d/sfp-backup
CMD ["cron", "-f"]

# Option B — Laravel scheduler (add to your docker CMD / supervisor)
# php artisan schedule:work</pre>
        </div>

    </div>
</div>

<script>
(function () {
    function el(id) { return document.getElementById(id); }

    function buildCron() {
        var freq    = el('sched-frequency').value;
        var expr    = freq === 'custom' ? (el('sched-custom').value.trim() || '* * * * *') : freq;
        var app     = (el('sched-app').value.trim()  || '/var/www/html').replace(/\/$/, '');
        var path    = el('sched-path').value.trim();
        var flags   = [];

        if (el('sched-config').checked)  flags.push('--config');
        if (el('sched-db').checked)      flags.push('--db');
        if (el('sched-storage').checked) flags.push('--storage');
        if (path) flags.push('--path=' + path);

        var cmd = expr + ' www-data php ' + app + '/artisan sfp:backup';
        if (flags.length) cmd += ' ' + flags.join(' ');
        cmd += ' >> /var/log/sfp-backup.log 2>&1';

        el('sched-output').textContent = cmd;
    }

    ['sched-frequency','sched-custom','sched-config','sched-db','sched-storage','sched-path','sched-app']
        .forEach(function (id) {
            var node = el(id);
            if (node) node.addEventListener('input', buildCron);
            if (node) node.addEventListener('change', buildCron);
        });

    el('sched-frequency').addEventListener('change', function () {
        el('sched-custom-wrap').classList.toggle('hidden', this.value !== 'custom');
        buildCron();
    });

    buildCron();
})();
</script>

@endsection
