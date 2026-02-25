<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suggest for Purchase — DC Public Library</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="bg-white border-b border-gray-200 px-6 py-4 mb-2">
        <p class="text-sm font-semibold text-gray-700">DC Public Library — Suggest for Purchase</p>
    </header>
    {{ $slot }}
    @livewireScripts
</body>
</html>
