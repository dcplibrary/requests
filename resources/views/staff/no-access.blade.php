<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied — {{ $appName }}</title>
    <link rel="stylesheet" href="{{ route('request.assets.css') }}?v={{ $requestsCssVersion ?? 'dev' }}">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="max-w-md w-full mx-4">

    {{-- Logo --}}
    <div class="flex justify-center mb-8">
        <x-requests::logo />
    </div>

    {{-- Card --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">

        {{-- Icon --}}
        <div class="flex justify-center mb-4">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
            </div>
        </div>

        <h1 class="text-xl font-semibold text-gray-900 mb-2">Access Denied</h1>

        <p class="text-gray-600">
            You do not have access to <span class="font-medium text-gray-800">{{ $appName }}</span>.
            Please contact the administrator to request access.
        </p>

    </div>

</div>

</body>
</html>
