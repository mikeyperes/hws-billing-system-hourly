{{-- Debug: Email / SMTP module --}}
@extends('layouts.app')
@section('title', 'Debug: Email')
@section('header', 'Debug: Email / SMTP')

@section('content')
<div class="max-w-3xl space-y-6">
    <a href="{{ route('debug.index') }}" class="text-sm text-blue-600 hover:underline">← Back to Debug Modules</a>

    {{-- Check Config --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Check SMTP Config</h2>
        <p class="text-sm text-gray-500 mb-4">Verifies SMTP settings are configured in .env / settings.</p>
        <form method="POST" action="{{ route('debug.email') }}">
            @csrf
            <input type="hidden" name="action" value="check_config">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                Check Config
            </button>
        </form>
    </div>

    {{-- Send Test Email --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Send Test Email</h2>
        <p class="text-sm text-gray-500 mb-4">Sends a test email using the configured SMTP settings.</p>
        <form method="POST" action="{{ route('debug.email') }}">
            @csrf
            <input type="hidden" name="action" value="send_test">
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Send To</label>
                <input type="email" name="to_email" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="test@example.com" required>
            </div>
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                Send Test
            </button>
        </form>
    </div>

    {{-- Results --}}
    @include('debug._results', ['results' => $results])
</div>
@endsection
