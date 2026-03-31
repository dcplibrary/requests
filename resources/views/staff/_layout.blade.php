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

    @yield('content')

    <script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js" crossorigin="anonymous"></script>
</x-dcpl::layouts.staff>
