{{-- Password reset form — user enters new password --}}
@extends('layouts.app')
@section('title', 'Reset Password — ' . config('hws.app_name'))

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-2">Reset Password</h2>
    <p class="text-sm text-gray-500 mb-4">Enter your new password below.</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        {{-- Hidden token --}}
        <input type="hidden" name="token" value="{{ $token }}">

        {{-- Email --}}
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
            <input type="email" name="email" id="email" value="{{ old('email', $email ?? '') }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required autofocus>
            @error('email')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- New Password --}}
        <div class="mb-4">
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
            <input type="password" name="password" id="password"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            @error('password')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Confirm Password --}}
        <div class="mb-4">
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
        </div>

        {{-- Submit --}}
        <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">
            Reset Password
        </button>
    </form>

    {{-- Back to login --}}
    <div class="mt-4 text-center">
        <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">Back to login</a>
    </div>
</div>
@endsection
