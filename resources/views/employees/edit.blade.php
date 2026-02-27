{{-- Employees: edit page with scan history --}}
@extends('layouts.app')

@section('title', 'Edit Employee — ' . config('hws.app_name'))
@section('header', 'Edit Employee: ' . $employee->name)

@section('content')

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- ═══ Edit Form ═══ --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <form method="POST" action="{{ route('employees.update', $employee) }}">
                @csrf
                @method('PUT')

                {{-- Employee name --}}
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Employee Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $employee->name) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                </div>

                {{-- Google Sheet URL or ID --}}
                <div class="mb-4">
                    <label for="google_sheet_id" class="block text-sm font-medium text-gray-700 mb-1">Google Sheet URL or ID</label>
                    <input type="text" name="google_sheet_id" id="google_sheet_id"
                        value="{{ old('google_sheet_id', $employee->google_sheet_id) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    {{-- Link to open the sheet --}}
                    <a href="{{ $employee->sheet_url }}" target="_blank" class="text-xs text-blue-600 hover:underline mt-1 inline-block">
                        Open in Google Sheets ↗
                    </a>
                </div>

                {{-- Scan start primary key (manual override) --}}
                <div class="mb-4">
                    <label for="scan_start_primary_key" class="block text-sm font-medium text-gray-700 mb-1">Scan Start Primary Key</label>
                    <input type="number" name="scan_start_primary_key" id="scan_start_primary_key" min="0"
                        value="{{ old('scan_start_primary_key', $employee->scan_start_primary_key) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <p class="text-xs text-gray-400 mt-1">Next scan will read rows with primary_key > this value.</p>
                </div>

                {{-- Last billing primary key (read-only info) --}}
                <div class="mb-4 p-3 bg-gray-50 rounded-lg text-sm">
                    <p class="text-gray-500">Last Billing Primary Key</p>
                    <p class="font-mono text-gray-700">{{ $employee->last_billing_primary_key }}</p>
                </div>

                {{-- Active toggle --}}
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1"
                            {{ old('is_active', $employee->is_active) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 mr-2">
                        <span class="text-sm text-gray-700">Active (included in billing scans)</span>
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">
                    Save Changes
                </button>
            </form>
        </div>

        {{-- ═══ Recent Scan History ═══ --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Recent Scans</h2>

            @if($recentScans->isEmpty())
                <p class="text-gray-500 text-sm">No scans recorded for this employee.</p>
            @else
                <div class="space-y-3">
                    @foreach($recentScans as $scan)
                        <div class="p-3 bg-gray-50 rounded-lg text-sm">
                            {{-- Status and timestamp --}}
                            <div class="flex justify-between items-center mb-1">
                                <span class="{{ $scan->status === 'completed' ? 'text-green-600' : 'text-red-600' }} font-medium">
                                    {{ ucfirst($scan->status) }}
                                </span>
                                <span class="text-gray-400 text-xs">{{ $scan->created_at->diffForHumans() }}</span>
                            </div>
                            {{-- Stats --}}
                            <p class="text-gray-600">
                                Scanned: {{ $scan->rows_scanned }} | Collected: {{ $scan->rows_collected }} | Errors: {{ $scan->error_count }}
                            </p>
                            {{-- Show errors if any --}}
                            @if($scan->hasErrors())
                                <details class="mt-2">
                                    <summary class="text-red-500 text-xs cursor-pointer">View errors</summary>
                                    <pre class="text-xs bg-red-50 p-2 rounded mt-1 overflow-x-auto">{{ json_encode($scan->errors, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

@endsection
