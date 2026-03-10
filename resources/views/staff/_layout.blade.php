<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Staff') — SFP</title>
    <link rel="stylesheet" href="{{ route('request.assets.css') }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
    <script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>
    <script>
        document.addEventListener('trix-initialize', function (e) {
            var input = e.target.inputElement;
            if (input && input.value) {
                e.target.editor.loadHTML(input.value);
            }
        });
    </script>
</head>
<body class="bg-gray-100 min-h-screen">

<nav class="bg-white border-b border-gray-200 px-6 flex items-stretch gap-6 h-14">

    {{-- Brand --}}
    <div class="flex items-center"><x-sfp::logo :href="route('request.staff.requests.index', ['kind' => 'sfp'])" /></div>

    {{-- Primary nav — tabs aligned to bottom of header --}}
    <div class="flex items-end gap-1">
        <a href="{{ route('request.staff.requests.index', ['kind' => 'sfp']) }}"
           class="px-3 py-2 rounded-t text-sm font-medium transition-colors border-b-2 -mb-px
                  {{ request()->routeIs('request.staff.requests.*') && request()->get('kind') !== 'ill'
                      ? 'bg-blue-50 text-blue-700 font-semibold border-blue-600'
                      : 'border-transparent text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
            SFP
        </a>
        @php
            $authUser = auth()->user();
            $isAdmin = method_exists($authUser, 'isAdmin') ? $authUser->isAdmin() : (($authUser->role ?? null) === 'admin');
            $openAccess = (bool) \Dcplibrary\Sfp\Models\Setting::get('requests_visibility_open_access', false);

            $sfpUser = $authUser instanceof \Dcplibrary\Sfp\Models\User
                ? $authUser
                : \Dcplibrary\Sfp\Models\User::where('email', $authUser->email ?? '')->first();

            $illGroupId = (int) \Dcplibrary\Sfp\Models\Setting::get('ill_selector_group_id', 0);
            $inIllGroup = $sfpUser && $illGroupId
                ? $sfpUser->selectorGroups()->whereKey($illGroupId)->exists()
                : false;

            $showIllTab = $isAdmin || $openAccess || $inIllGroup;
        @endphp
        @if($showIllTab)
            <a href="{{ route('request.staff.requests.index', ['kind' => 'ill']) }}"
               class="px-3 py-2 rounded-t text-sm font-medium transition-colors border-b-2 -mb-px
                      {{ request()->routeIs('request.staff.requests.*') && request()->get('kind') === 'ill'
                          ? 'bg-blue-50 text-blue-700 font-semibold border-blue-600'
                          : 'border-transparent text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                ILL
            </a>
        @endif
        <a href="{{ route('request.staff.patrons.index') }}"
           class="px-3 py-2 rounded-t text-sm font-medium transition-colors border-b-2 -mb-px
                  {{ request()->routeIs('request.staff.patrons.*')
                      ? 'bg-blue-50 text-blue-700 font-semibold border-blue-600'
                      : 'border-transparent text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
            Patrons
        </a>
        <a href="{{ route('request.staff.titles.index') }}"
           class="px-3 py-2 rounded-t text-sm font-medium transition-colors border-b-2 -mb-px
                  {{ request()->routeIs('request.staff.titles.*')
                      ? 'bg-blue-50 text-blue-700 font-semibold border-blue-600'
                      : 'border-transparent text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
            Titles
        </a>
    </div>

    {{-- User avatar + dropdown pushed right --}}
    <div class="ml-auto flex items-center gap-3">
        @auth
        <a href="{{ asset('sfp-selector-help.html') }}"
           target="_blank"
           rel="noopener noreferrer"
           class="inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-200 text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors"
           aria-label="Help"
           title="Help"
           onclick="try { window.open(this.href, 'sfpHelp', 'width=980,height=720,scrollbars=yes'); return false; } catch (e) { return true; }">
            {{-- Heroicons (outline): question-mark-circle --}}
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.251 1.09-1.251 1.902v.75m0 3h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
        </a>
        @php
            $nameParts = explode(' ', trim($authUser->name ?? ''));
            $initials = strtoupper(substr($nameParts[0] ?? '', 0, 1));
            if (count($nameParts) > 1) {
                $initials .= strtoupper(substr(end($nameParts), 0, 1));
            }
        @endphp
        <div class="relative" x-data="{ open: false }" @click.away="open = false">

            <button @click="open = !open"
                    class="flex items-center justify-center w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 text-white font-semibold text-sm hover:opacity-90 transition-opacity focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">
                {{ $initials ?: 'U' }}
            </button>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute right-0 mt-2 w-56 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-gray-900/10 z-50"
                 style="display:none">

                {{-- User info --}}
                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-900">{{ $authUser->name }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $authUser->email }}</p>
                    @if($authUser->role)
                        <p class="text-xs text-blue-600 mt-1 font-medium">{{ ucfirst($authUser->role) }}</p>
                    @endif
                </div>

                {{-- Settings — admin only --}}
                @if($isAdmin)
                <div class="py-1 border-b border-gray-100">
                    <a href="{{ route('request.staff.settings.index') }}"
                       class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors
                              {{ request()->routeIs('request.staff.settings.*', 'request.staff.material-types.*', 'request.staff.audiences.*', 'request.staff.statuses.*', 'request.staff.users.*', 'request.staff.groups.*') ? 'font-semibold' : '' }}">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Settings
                    </a>
                </div>
                @endif

                {{-- Logout --}}
                <div class="py-1">
                    <form method="POST" action="{{ route('entra.logout') }}">
                        @csrf
                        <button type="submit"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Sign out
                        </button>
                    </form>
                </div>

            </div>
        </div>
        @endauth
    </div>

</nav>

<main class="max-w-7xl mx-auto px-6 py-8">

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')

</main>
</body>
</html>
