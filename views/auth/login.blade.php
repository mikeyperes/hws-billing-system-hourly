{{-- Login page — uses guest layout (no sidebar) --}}
@extends('layouts.app')
@section('title', 'Login — ' . config('hws.app_name'))

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Sign In</h2>

    {{-- Login form --}}
    <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- Email --}}
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required autofocus>
            @error('email')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div class="mb-4">
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="password" id="password"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
        </div>

        {{-- Remember me --}}
        <div class="mb-4 flex items-center justify-between">
            <label class="inline-flex items-center">
                <input type="checkbox" name="remember" class="rounded border-gray-300 text-blue-600">
                <span class="ml-2 text-sm text-gray-600">Remember me</span>
            </label>
            <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:underline">
                Forgot password?
            </a>
        </div>

        {{-- Submit --}}
        <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">
            Sign In
        </button>
    </form>
</div>
@endsection
