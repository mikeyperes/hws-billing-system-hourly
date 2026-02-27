{{-- Employee edit page — two-column layout --}}
{{-- Left: edit form + validate sheet panel --}}
{{-- Right: recent scan history --}}
@extends('layouts.app')
@section('title', 'Edit Employee')
@section('header', 'Edit Employee: ' . $employee->name)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ── Left Column: Edit Form + Validate ── --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Edit Form --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Employee Details</h2>

            {{-- Update form — posts to employees.update --}}
            <form method="POST" action="{{ route('employees.update', $employee) }}">
                @csrf
                @method('PUT')

                {{-- Employee Name --}}
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Employee Name</label>
                    <input type="text" name="name" id="name"
                        value="{{ old('name', $employee->name) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        required>
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Google Sheet URL or ID --}}
                <div class="mb-4">
                    <label for="google_sheet_id" class="block text-sm font-medium text-gray-700 mb-1">Google Sheet URL or ID</label>
                    <input type="text" name="google_sheet_id" id="google_sheet_id"
                        value="{{ old('google_sheet_id', $employee->google_sheet_id) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="https://docs.google.com/spreadsheets/d/abc123/edit"
                        required>
                    <p class="text-xs text-gray-400 mt-1">
                        The sheet must be shared with the service account ({{ config('hws.google.service_account_email', 'not configured') }}) as Editor.
                    </p>
                    @error('google_sheet_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Scan Start Primary Key (cursor override) --}}
                <div class="mb-4">
                    <label for="scan_start_primary_key" class="block text-sm font-medium text-gray-700 mb-1">Scan Cursor (Primary Key)</label>
                    <input type="number" name="scan_start_primary_key" id="scan_start_primary_key"
                        value="{{ old('scan_start_primary_key', $employee->scan_start_primary_key) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        min="0">
                    <p class="text-xs text-gray-400 mt-1">Next scan reads rows with primary_key greater than this value. Set to 0 to scan all rows.</p>
                </div>

                {{-- Active Toggle --}}
                <div class="mb-6">
                    <label class="inline-flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                            {{ $employee->is_active ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600">
                        <span class="ml-2 text-sm text-gray-700">Active</span>
                    </label>
                    <p class="text-xs text-gray-400 mt-1">Inactive employees are skipped during billing scans.</p>
                </div>

                {{-- Submit + Cancel --}}
                <div class="flex items-center gap-3">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">
                        Save Changes
                    </button>
                    <a href="{{ route('employees.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        {{-- ── Validate Sheet Panel ── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Google Sheet Validation</h2>
                {{-- Validate button — posts to employees.validate --}}
                <form method="POST" action="{{ route('employees.validate', $employee) }}">
                    @csrf
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                        ▶ Validate Sheet
                    </button>
                </form>
            </div>
            <p class="text-sm text-gray-500 mb-4">Checks: credentials file exists, service account can access the sheet, required column headers are present, and data rows exist.</p>

            {{-- Sheet link for quick access --}}
            <p class="text-sm mb-4">
                <a href="https://docs.google.com/spreadsheets/d/{{ $employee->google_sheet_id }}" target="_blank" class="text-blue-600 hover:underline">
                    Open Sheet in Google Sheets ↗
                </a>
            </p>

            {{-- Validation Results (shown after clicking Validate) --}}
            @if(session('validation_results'))
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-600">Status</th>
                                <th class="px-4 py-2 text-left text-gray-600">Check</th>
                                <th class="px-4 py-2 text-left text-gray-600">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(session('validation_results') as $result)
                                <tr class="border-t border-gray-100">
                                    <td class="px-4 py-2">
                                        @if($result['pass'])
                                            <span class="text-green-600 font-bold">✅ PASS</span>
                                        @else
                                            <span class="text-red-600 font-bold">❌ FAIL</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ $result['check'] }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $result['message'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Summary --}}
                @php
                    $passed = collect(session('validation_results'))->where('pass', true)->count();
                    $failed = collect(session('validation_results'))->where('pass', false)->count();
                    $total = count(session('validation_results'));
                @endphp
                <div class="mt-3 text-sm {{ $failed > 0 ? 'text-red-600' : 'text-green-600' }} font-medium">
                    {{ $passed }}/{{ $total }} checks passed.
                    @if($failed > 0)
                        Fix the failed checks above before running a billing scan.
                    @else
                        Sheet is ready for billing scans.
                    @endif
                </div>
            @else
                <div class="text-sm text-gray-400 italic">Click "Validate Sheet" to run checks.</div>
            @endif
        </div>

    </div>

    {{-- ── Right Column: Scan History ── --}}
    <div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Scan History</h2>

            @if($recentScans->isEmpty())
                {{-- No scans yet --}}
                <p class="text-sm text-gray-400 italic">No scans recorded yet.</p>
            @else
                {{-- Scan history list --}}
                <div class="space-y-3">
                    @foreach($recentScans as $scan)
                        <div class="border border-gray-100 rounded-lg p-3">
                            {{-- Scan date and status --}}
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-500">
                                    {{ $scan->created_at->format('M j, Y g:i A') }}
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full
                                    {{ $scan->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $scan->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                                    {{ $scan->status === 'running' ? 'bg-yellow-100 text-yellow-700' : '' }}">
                                    {{ ucfirst($scan->status) }}
                                </span>
                            </div>
                            {{-- Scan stats --}}
                            <p class="text-sm text-gray-700">
                                {{ $scan->rows_scanned }} scanned, {{ $scan->rows_collected }} collected
                            </p>
                            {{-- Errors if any --}}
                            @if($scan->errors && count($scan->errors) > 0)
                                <p class="text-xs text-red-500 mt-1">
                                    {{ count($scan->errors) }} error(s)
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Employee Info --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Info</h2>
            <dl class="text-sm space-y-2">
                <div>
                    <dt class="text-gray-500">Created</dt>
                    <dd class="text-gray-900">{{ $employee->created_at->format('M j, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Last Billing PK</dt>
                    <dd class="text-gray-900">{{ $employee->last_billing_primary_key }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Sheet ID</dt>
                    <dd class="text-gray-900 break-all text-xs">{{ $employee->google_sheet_id }}</dd>
                </div>
            </dl>
        </div>
    </div>

</div>
@endsection
