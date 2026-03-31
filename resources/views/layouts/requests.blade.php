<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suggest a Purchase — Daviess County Public Library</title>

    {{-- Google Fonts: Outfit (matches the staff interface) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Trix base before package CSS so requests.css can override toolbar wrap + borders --}}
    <link rel="stylesheet" href="https://unpkg.com/trix@2.0.0/dist/trix.css" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ route('request.assets.css') }}?v={{ $requestsCssVersion ?? 'dev' }}">
    @livewireStyles
    @stack('head')
</head>
<body class="bg-gray-50 min-h-screen font-outfit text-dcpl-text">
    <header class="w-full bg-white shadow-sm">
        {{-- Top bar: logo --}}
        @php
            $libraryWebsite = \Dcplibrary\Requests\Models\Setting::get('library_website_url', '');
        @endphp
        <div class="px-6 py-3 flex items-center border-b border-gray-200">
            <x-dcpl::logo :show-name="false" :href="$libraryWebsite ?: url('/')" />
            <span class="ml-4 font-outfit text-2xl font-light tracking-widest uppercase text-dcpl-text select-none">
                {{ config('app.name') }}
            </span>
        </div>
        {{-- DCPL blue bar (matches staff interface) --}}
        <div class="w-full flex items-stretch justify-end" style="background-color: var(--dcpl-blue, #0075A3);">
            {{-- Back to library website (right-aligned) --}}
            @if($libraryWebsite)
            <a href="{{ $libraryWebsite }}"
               class="inline-flex items-center px-5 py-3 text-sm font-medium text-white/80 hover:text-white hover:bg-black/10 transition-colors whitespace-nowrap">
                &larr; Back to DCPL Website
            </a>
            @else
            {{-- Keep bar visible even without back link --}}
            <div class="h-10"></div>
            @endif
        </div>
    </header>
    {{ $slot }}
    @livewireScripts
</body>
</html>
