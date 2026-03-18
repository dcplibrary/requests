{{--
    Show-page breadcrumb header with back link, title, and optional badges.

    Usage:
        <x-requests::page-header
            :back-url="route('request.staff.requests.index')"
            back-label="Back to requests"
            title="Request #42"
        >
            <x-requests::kind-badge kind="sfp" />
        </x-requests::page-header>

    Props:
        backUrl   — string      — URL for the back link
        backLabel — string      — text for the back link (without arrow)
        title     — string      — page heading text
        id        — string|null — optional ID shown as muted monospace after title

    Slot:
        Optional trailing content (badges, timestamps, etc.)
--}}
@props([
    'backUrl'   => '#',
    'backLabel' => 'Back',
    'title'     => '',
    'id'        => null,
])

<div class="mb-6 flex items-center gap-3">
    <a href="{{ $backUrl }}" class="text-sm hover:underline" style="color: #0075A3;">&larr; {{ $backLabel }}</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-semibold" style="color: #4d6375;">{{ $title }}</h1>
    @if($id)
        <span class="text-sm text-gray-400 font-mono">#{{ $id }}</span>
    @endif
    {{ $slot }}
</div>
