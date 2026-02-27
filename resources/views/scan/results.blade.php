{{-- Scan: results page — review grouped items before creating invoices --}}
@extends('layouts.app')

@section('title', 'Scan Results — ' . config('hws.app_name'))
@section('header', 'Scan Results')

@section('content')

    {{-- ═══ Summary Bar ═══ --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-gray-500 text-sm">Rows Scanned</p>
            <p class="text-2xl font-bold">{{ $totalRowsScanned }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-gray-500 text-sm">Rows Collected</p>
            <p class="text-2xl font-bold text-green-600">{{ $totalRowsCollected }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-gray-500 text-sm">Clients Found</p>
            <p class="text-2xl font-bold">{{ count($groupedByClient) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-gray-500 text-sm">Errors</p>
            <p class="text-2xl font-bold {{ count($errors) > 0 ? 'text-red-600' : '' }}">{{ count($errors) }}</p>
        </div>
    </div>

    {{-- ═══ Grouped Results by Client ═══ --}}
    @if(!empty($groupedByClient))
        @foreach($groupedByClient as $clientName => $rows)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
                {{-- Client header with totals --}}
                @php
                    $clientMinutes = collect($rows)->sum('time_minutes');
                    $clientHours = round($clientMinutes / 60, 2);
                @endphp
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-semibold text-gray-800">{{ $clientName }}</h3>
                    <span class="text-sm text-gray-500">{{ count($rows) }} rows — {{ $clientHours }} hrs</span>
                </div>

                {{-- Row detail table --}}
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">Employee</th>
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">PK</th>
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">Date</th>
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">Description</th>
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">Minutes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr class="border-b border-gray-100">
                                <td class="py-2 px-3">{{ $row['employee_name'] }}</td>
                                <td class="py-2 px-3 font-mono text-xs text-gray-400">{{ $row['primary_key'] }}</td>
                                <td class="py-2 px-3">{{ $row['date'] }}</td>
                                <td class="py-2 px-3">{{ Str::limit($row['description'], 60) }}</td>
                                <td class="py-2 px-3 text-right">{{ $row['time_minutes'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach

        {{-- Create invoices button --}}
        <div class="mt-6 text-center">
            <form method="POST" action="{{ route('scan.create-invoices') }}">
                @csrf
                <button type="submit" class="bg-green-600 text-white px-8 py-3 rounded-lg text-lg hover:bg-green-700"
                    onclick="return confirm('Create {{ count($groupedByClient) }} draft invoice(s) on Stripe?')">
                    ✅ Create {{ count($groupedByClient) }} Invoice(s)
                </button>
            </form>
        </div>
    @else
        {{-- No results --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No billable rows found across any employee sheets.</p>
            <a href="{{ route('scan.index') }}" class="text-blue-600 hover:underline text-sm mt-2 inline-block">← Back</a>
        </div>
    @endif

    {{-- ═══ Errors Panel ═══ --}}
    @if(!empty($errors))
        <div class="bg-red-50 rounded-xl border border-red-200 p-6 mt-6">
            <h3 class="font-semibold text-red-800 mb-3">Scan Errors ({{ count($errors) }})</h3>
            <div class="space-y-2">
                @foreach($errors as $error)
                    <div class="text-sm text-red-700 p-2 bg-red-100 rounded">
                        <span class="font-medium">[{{ $error['type'] }}]</span>
                        Row {{ $error['row'] ?? '?' }} — {{ $error['message'] }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif

@endsection
