@php
    $authUser   = auth()->user();
    $isAdmin    = method_exists($authUser, 'isAdmin')
        ? $authUser->isAdmin()
        : (($authUser->role ?? null) === 'admin');

    $openAccess = (bool) \Dcplibrary\Requests\Models\Setting::get('requests_visibility_open_access', false);

    $staffUser = $authUser instanceof \Dcplibrary\Requests\Models\User
        ? $authUser
        : \Dcplibrary\Requests\Models\User::where('email', $authUser->email ?? '')->first();

    $illGroupId = (int) \Dcplibrary\Requests\Models\Setting::get('ill_selector_group_id', 0);
    $inIllGroup = $staffUser && $illGroupId
        ? $staffUser->selectorGroups()->whereKey($illGroupId)->exists()
        : false;

    $isStaffViewer = $staffUser && $staffUser->isStaff();
    $canEdit       = $staffUser && $staffUser->canEdit();

    $showIllTab = $isAdmin || $openAccess || $inIllGroup || $isStaffViewer;

    // Share $canEdit with all yielded views
    \Illuminate\Support\Facades\View::share('canEdit', $canEdit);
    \Illuminate\Support\Facades\View::share('isStaffViewer', $isStaffViewer);

    $navItems = [
        [
            'label'   => 'Suggestions for purchase',
            'href'    => route('request.staff.requests.index', ['kind' => 'sfp']),
            'active'  => request()->routeIs('request.staff.requests.*') && request()->get('kind') !== 'ill',
            'visible' => true,
        ],
        [
            'label'   => 'Interlibrary loans',
            'href'    => route('request.staff.requests.index', ['kind' => 'ill']),
            'active'  => request()->routeIs('request.staff.requests.*') && request()->get('kind') === 'ill',
            'visible' => $showIllTab,
        ],
        [
            'label'   => 'Patrons',
            'href'    => route('request.staff.patrons.index'),
            'active'  => request()->routeIs('request.staff.patrons.*'),
            'visible' => true,
        ],
        [
            'label'   => 'Titles',
            'href'    => route('request.staff.titles.index'),
            'active'  => request()->routeIs('request.staff.titles.*'),
            'visible' => true,
        ],
    ];

    $visibleNav = collect($navItems)->filter(fn ($i) => $i['visible'])->values();
@endphp

<x-dcpl::layouts.staff
    :logo-href="route('request.staff.requests.index', ['kind' => 'sfp'])"
    :logout-route="route('entra.logout')"
    :settings-route="$isAdmin ? route('request.staff.settings.index') : null"
    :help-url="asset('requests-selector-help.html')"
>
    <x-slot:package-css>
        {{-- Trix base first; requests.css overrides toolbar wrap + colors. --}}
        <link rel="stylesheet" href="https://unpkg.com/trix@2.0.0/dist/trix.css" crossorigin="anonymous">
        <link rel="stylesheet" href="{{ route('request.assets.css') }}?v={{ $requestsCssVersion ?? 'dev' }}">
    </x-slot:package-css>
    <x-slot:nav>
        @foreach($visibleNav as $item)
            <x-dcpl::staff-nav-tab
                :href="$item['href']"
                :label="$item['label']"
                :active="$item['active']"
            />
        @endforeach
    </x-slot:nav>

    {{-- Admins already have a Settings item that opens the admin area. --}}
    @unless($isAdmin)
    <x-slot:dropdownItems>
        <a href="{{ route('request.staff.account.edit') }}"
           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Settings
        </a>
    </x-slot:dropdownItems>
    @endunless

    @yield('content')

    <script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js" crossorigin="anonymous"></script>
</x-dcpl::layouts.staff>
