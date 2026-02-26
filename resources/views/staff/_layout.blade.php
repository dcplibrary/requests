<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Staff') — SFP</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

<nav class="bg-white border-b border-gray-200 px-6 flex items-center gap-6 h-14">

    {{-- Brand --}}
    <span class="font-bold text-blue-700 text-lg tracking-tight shrink-0">SFP Staff</span>

    {{-- Primary nav — visible to all --}}
    <div class="flex items-center gap-1">
        <a href="{{ route('sfp.staff.requests.index') }}"
           class="px-3 py-2 rounded-md text-sm font-medium transition-colors
                  {{ request()->routeIs('sfp.staff.requests.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
            Requests
        </a>
        <a href="{{ route('sfp.staff.patrons.index') }}"
           class="px-3 py-2 rounded-md text-sm font-medium transition-colors
                  {{ request()->routeIs('sfp.staff.patrons.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
            Patrons
        </a>
        <a href="{{ route('sfp.staff.titles.index') }}"
           class="px-3 py-2 rounded-md text-sm font-medium transition-colors
                  {{ request()->routeIs('sfp.staff.titles.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
            Titles
        </a>
    </div>

    {{-- User avatar + dropdown pushed right --}}
    <div class="ml-auto">
        @auth
        @php
            $authUser = auth()->user();
            $nameParts = explode(' ', trim($authUser->name ?? ''));
            $initials = strtoupper(substr($nameParts[0] ?? '', 0, 1));
            if (count($nameParts) > 1) {
                $initials .= strtoupper(substr(end($nameParts), 0, 1));
            }
            $isAdmin = method_exists($authUser, 'isAdmin') ? $authUser->isAdmin() : ($authUser->role === 'admin');
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
                    <a href="{{ route('sfp.staff.settings.index') }}"
                       class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors
                              {{ request()->routeIs('sfp.staff.settings.*', 'sfp.staff.material-types.*', 'sfp.staff.audiences.*', 'sfp.staff.statuses.*', 'sfp.staff.users.*', 'sfp.staff.groups.*') ? 'font-semibold' : '' }}">
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
