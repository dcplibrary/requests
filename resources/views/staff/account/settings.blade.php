@extends('requests::staff._layout')

@section('title', 'Settings')

@section('content')
<div class="max-w-3xl">
    <h1 class="text-xl font-bold text-gray-900 mb-4">Settings</h1>
    @include('requests::staff.account._email-preference', ['user' => $user])
</div>
@endsection
