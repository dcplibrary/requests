<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suggest a Purchase — Daviess County Public Library</title>
    <link rel="stylesheet" href="{{ route('request.assets.css') }}">
    @livewireStyles
    @stack('head')
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="bg-white border-b border-gray-200 px-6 py-4 mb-2">
        <x-requests::logo />
    </header>
    {{ $slot }}
    @livewireScripts
</body>
</html>
