<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Staff') — SFP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<nav class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-6">
    <span class="font-bold text-blue-700 text-lg tracking-tight">SFP Staff</span>
    <a href="{{ route('sfp.staff.requests.index') }}"
       class="text-sm text-gray-600 hover:text-blue-700 {{ request()->routeIs('sfp.staff.requests.*') ? 'font-semibold text-blue-700' : '' }}">
        Requests
    </a>
    <a href="{{ route('sfp.staff.settings.index') }}"
       class="text-sm text-gray-600 hover:text-blue-700 {{ request()->routeIs('sfp.staff.settings.*') ? 'font-semibold text-blue-700' : '' }}">
        Settings
    </a>
    <span class="text-gray-300">|</span>
    <a href="{{ route('sfp.staff.material-types.index') }}"
       class="text-sm text-gray-600 hover:text-blue-700 {{ request()->routeIs('sfp.staff.material-types.*') ? 'font-semibold text-blue-700' : '' }}">
        Material Types
    </a>
    <a href="{{ route('sfp.staff.audiences.index') }}"
       class="text-sm text-gray-600 hover:text-blue-700 {{ request()->routeIs('sfp.staff.audiences.*') ? 'font-semibold text-blue-700' : '' }}">
        Audiences
    </a>
    <a href="{{ route('sfp.staff.statuses.index') }}"
       class="text-sm text-gray-600 hover:text-blue-700 {{ request()->routeIs('sfp.staff.statuses.*') ? 'font-semibold text-blue-700' : '' }}">
        Statuses
    </a>
    <a href="{{ route('sfp.staff.users.index') }}"
       class="text-sm text-gray-600 hover:text-blue-700 {{ request()->routeIs('sfp.staff.users.*') ? 'font-semibold text-blue-700' : '' }}">
        Users
    </a>
    <a href="{{ route('sfp.staff.groups.index') }}"
       class="text-sm text-gray-600 hover:text-blue-700 {{ request()->routeIs('sfp.staff.groups.*') ? 'font-semibold text-blue-700' : '' }}">
        Groups
    </a>
    <div class="ml-auto flex items-center gap-4">
        <span class="text-sm text-gray-500">{{ auth()->user()?->name }}</span>
        <form method="POST" action="{{ route('entra.logout') }}">
            @csrf
            <button class="text-sm text-gray-500 hover:text-red-600">Sign out</button>
        </form>
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
