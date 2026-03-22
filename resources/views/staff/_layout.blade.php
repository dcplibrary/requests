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

    $showIllTab = $isAdmin || $openAccess || $inIllGroup;

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

    @yield('content')

</x-dcpl::layouts.staff>
