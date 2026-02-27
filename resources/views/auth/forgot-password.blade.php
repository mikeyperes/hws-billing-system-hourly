{{-- Forgot password — enter email to receive reset link --}}
@extends('layouts.app')
@section('title', 'Forgot Password — ' . config('hws.app_name'))

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-2">Forgot Password</h2>
    <p class="text-sm text-gray-500 mb-4">Enter your email and we'll send you a password reset link.</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        {{-- Email --}}
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required autofocus>
            @error('email')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Submit --}}
        <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">
            Send Reset Link
        </button>
    </form>

    {{-- Back to login --}}
    <div class="mt-4 text-center">
        <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">Back to login</a>
    </div>
</div>
@endsection
