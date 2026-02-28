{{-- Debug: Stripe module --}}
@extends('layouts.app')
@section('title', 'Debug: Stripe')
@section('header', 'Debug: Stripe API')

@section('content')
<div class="max-w-3xl space-y-6">
    <a href="{{ route('debug.index') }}" class="text-sm text-blue-600 hover:underline">← Back to Debug Modules</a>

    {{-- Test API Key --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Test API Key</h2>
        <p class="text-sm text-gray-500 mb-4">Verifies the Stripe secret key is set and can connect to the Stripe API.</p>
        <form method="POST" action="{{ route('debug.stripe') }}">
            @csrf
            <input type="hidden" name="action" value="test_key">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                Test Connection
            </button>
        </form>
    </div>

    {{-- Results --}}
    @include('debug._results', ['results' => $results])
</div>
@endsection
