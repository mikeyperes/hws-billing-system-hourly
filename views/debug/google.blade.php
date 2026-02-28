{{-- Debug: Google Sheets module --}}
@extends('layouts.app')
@section('title', 'Debug: Google Sheets')
@section('header', 'Debug: Google Sheets')

@section('content')
<div class="max-w-3xl space-y-6">
    <a href="{{ route('debug.index') }}" class="text-sm text-blue-600 hover:underline">← Back to Debug Modules</a>

    {{-- Check Credentials --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Check Credentials</h2>
        <p class="text-sm text-gray-500 mb-4">Verifies the Google service account JSON file exists and is valid.</p>
        <form method="POST" action="{{ route('debug.google') }}">
            @csrf
            <input type="hidden" name="action" value="check_credentials">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                Run Check
            </button>
        </form>
    </div>

    {{-- Test Sheet Access --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Test Sheet Access</h2>
        <p class="text-sm text-gray-500 mb-4">Tests read access to a specific Google Sheet.</p>
        <form method="POST" action="{{ route('debug.google') }}">
            @csrf
            <input type="hidden" name="action" value="test_sheet">
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Sheet URL or ID</label>
                <input type="text" name="sheet_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="https://docs.google.com/spreadsheets/d/abc123/edit" required>
            </div>
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                Test Access
            </button>
        </form>
    </div>

    {{-- Results --}}
    @include('debug._results', ['results' => $results])
</div>
@endsection
