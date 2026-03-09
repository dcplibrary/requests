@extends('sfp::staff.settings._layout')
@section('title', 'Delete template')
@section('settings-content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('request.staff.settings.notifications', ['tab' => 'emails']) }}" class="text-sm text-blue-600 hover:underline">&larr; Emails</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">Delete template</h1>
</div>

<div class="bg-white rounded-lg border border-gray-200 p-6 max-w-lg">
    <p class="text-gray-700 mb-4">Delete the template <strong>{{ $template->name }}</strong>? This cannot be undone.</p>
    <form method="POST" action="{{ route('request.staff.patron-status-templates.destroy', $template) }}" class="flex gap-3">
        @csrf
        @method('DELETE')
        <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700">Delete</button>
        <a href="{{ route('request.staff.settings.notifications', ['tab' => 'emails']) }}" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Cancel</a>
    </form>
</div>
@endsection
