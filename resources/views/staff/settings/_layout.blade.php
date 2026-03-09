@extends('sfp::staff._layout')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
</div>

<div class="flex gap-6">

    {{-- Submenu sidebar --}}
    <nav class="w-48 shrink-0">
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            @php
            $settingsNav = [
                ['label' => 'General',        'route' => 'request.staff.settings.index',           'pattern' => 'request.staff.settings.index'],
                ['label' => 'Form Fields',    'route' => 'request.staff.settings.form-fields',     'pattern' => 'request.staff.settings.form-fields*'],
                ['label' => 'Custom Fields',  'route' => 'request.staff.settings.custom-fields',   'pattern' => 'request.staff.settings.custom-fields*'],
                ['label' => 'Notifications',  'route' => 'request.staff.settings.notifications',   'pattern' => 'request.staff.settings.notifications*'],
                ['label' => 'Catalog',        'route' => 'request.staff.catalog.index',            'pattern' => 'request.staff.catalog.*'],
                ['label' => 'Statuses',       'route' => 'request.staff.statuses.index',           'pattern' => 'request.staff.statuses.*'],
                ['label' => 'Users',          'route' => 'request.staff.users.index',              'pattern' => 'request.staff.users.*'],
                ['label' => 'Groups',         'route' => 'request.staff.groups.index',             'pattern' => 'request.staff.groups.*'],
                ['label' => 'Backups',        'route' => 'request.staff.backups.index',            'pattern' => 'request.staff.backups.*'],
            ];
            @endphp
            @foreach($settingsNav as $item)
            <a href="{{ route($item['route']) }}"
               class="block px-4 py-2.5 text-sm border-b border-gray-100 last:border-b-0 transition-colors
                      {{ request()->routeIs($item['pattern'])
                         ? 'bg-blue-50 text-blue-700 font-semibold'
                         : 'text-gray-700 hover:bg-gray-50' }}">
                {{ $item['label'] }}
            </a>
            @endforeach

            <div class="border-t border-gray-200 p-3">
                <a href="{{ asset('sfp-settings-help.html') }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="text-sm text-blue-600 hover:underline"
                   onclick="try { window.open(this.href, 'sfpSettingsHelp', 'width=1080,height=740,scrollbars=yes'); return false; } catch (e) { return true; }">
                    Help
                </a>
            </div>
        </div>
    </nav>

    {{-- Page content --}}
    <div class="flex-1 min-w-0">
        @yield('settings-content')
    </div>

</div>
@endsection
