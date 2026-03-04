@extends('sfp::staff._layout')

@section('title', $title)

@section('content')
<div class="mb-6 flex items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
        <p class="text-sm text-gray-500 mt-1">Opens in a new tab from the Help icon.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('sfp.staff.help', ['page' => 'user']) }}"
           class="px-3 py-2 rounded-md text-sm font-medium border border-gray-300 text-gray-700 hover:bg-gray-50 {{ $page === 'user' ? 'bg-gray-50' : '' }}">
            User help
        </a>
        @if(auth()->user() && (method_exists(auth()->user(), 'isAdmin') ? auth()->user()->isAdmin() : (auth()->user()->role ?? null) === 'admin'))
        <a href="{{ route('sfp.staff.help', ['page' => 'admin']) }}"
           class="px-3 py-2 rounded-md text-sm font-medium border border-gray-300 text-gray-700 hover:bg-gray-50 {{ $page === 'admin' ? 'bg-gray-50' : '' }}">
            Admin docs
        </a>
        @endif
    </div>
</div>

<article class="bg-white rounded-lg border border-gray-200 p-6">
    <div class="prose prose-slate max-w-none">
        {!! $html !!}
    </div>
</article>
@endsection

