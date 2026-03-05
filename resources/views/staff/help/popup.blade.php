<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — SFP Help</title>
    <link rel="stylesheet" href="{{ route('sfp.assets.css') }}">
</head>
<body class="bg-gray-100 min-h-screen">
    <main class="max-w-3xl mx-auto px-6 py-6">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h1 class="text-lg font-bold text-gray-900">{{ $title }}</h1>
            <a href="{{ route('sfp.staff.help', ['page' => $page]) }}"
               target="_blank"
               rel="noopener noreferrer"
               class="text-sm text-blue-600 hover:underline">
                Open full page
            </a>
        </div>

        <article class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="prose prose-slate max-w-none">
                {!! $html !!}
            </div>
        </article>
    </main>
</body>
</html>

