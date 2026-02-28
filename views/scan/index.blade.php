{{-- Scan: main page with run scan button --}}
@extends('layouts.app')

@section('title', 'Billing Scan — ' . config('hws.app_name'))
@section('header', 'Billing Scan')

@section('content')

    <div class="max-w-xl mx-auto text-center">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            {{-- Icon --}}
            <div class="text-6xl mb-4">📊</div>
            {{-- Title --}}
            <h2 class="text-xl font-semibold text-gray-800 mb-2">Run Billing Scan</h2>
            {{-- Description --}}
            <p class="text-gray-500 text-sm mb-6">
                Scans all active employee Google Sheets for new billable rows.
                Rows are validated, grouped by client, and presented for review before creating invoices.
            </p>
            {{-- Run scan form --}}
            <form method="POST" action="{{ route('scan.run') }}">
                @csrf
                <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg text-lg hover:bg-blue-700">
                    🔍 Run Scan Now
                </button>
            </form>
        </div>
    </div>

@endsection
