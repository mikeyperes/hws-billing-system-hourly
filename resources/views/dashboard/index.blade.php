{{-- Dashboard: main overview page --}}
{{-- Shows invoice stats, low-credit flags, employee overview, and recent scans --}}
@extends('layouts.app')

@section('title', 'Dashboard — ' . config('hws.app_name'))
@section('header', 'Dashboard')

@section('content')

    {{-- ═══ Invoice Summary Cards ═══ --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

        {{-- Draft invoices card --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <p class="text-sm text-gray-500 mb-1">Draft Invoices</p>
            <p class="text-3xl font-bold text-gray-800">{{ $invoiceCounts['draft'] }}</p>
            <p class="text-sm text-gray-400 mt-1">${{ number_format($invoiceAmounts['draft'], 2) }} total</p>
        </div>

        {{-- Sent/outstanding invoices card --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <p class="text-sm text-gray-500 mb-1">Outstanding</p>
            <p class="text-3xl font-bold text-yellow-600">{{ $invoiceCounts['sent'] }}</p>
            <p class="text-sm text-gray-400 mt-1">${{ number_format($invoiceAmounts['sent'], 2) }} awaiting payment</p>
        </div>

        {{-- Paid invoices card --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <p class="text-sm text-gray-500 mb-1">Paid</p>
            <p class="text-3xl font-bold text-green-600">{{ $invoiceCounts['paid'] }}</p>
            <p class="text-sm text-gray-400 mt-1">${{ number_format($invoiceAmounts['paid'], 2) }} collected</p>
        </div>
    </div>

    {{-- ═══ Two-Column Layout: Flags + Employees ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

        {{-- Low Credit Flags Panel --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">⚠️ Low Credit Clients</h2>
            @if($lowCreditClients->isEmpty())
                <p class="text-gray-500 text-sm">No clients with low credit balance.</p>
            @else
                <div class="space-y-3">
                    @foreach($lowCreditClients as $client)
                        <div class="flex justify-between items-center p-3 bg-yellow-50 rounded-lg border border-yellow-100">
                            <div>
                                <a href="{{ route('clients.edit', $client) }}" class="font-medium text-gray-800 hover:text-blue-600">{{ $client->name }}</a>
                                <p class="text-sm text-gray-500">{{ $client->credit_balance_hours }} hrs remaining</p>
                            </div>
                            @if($client->credit_alert_sent)
                                <span class="text-xs bg-yellow-200 text-yellow-800 px-2 py-1 rounded">Alert Sent</span>
                            @else
                                <span class="text-xs bg-red-200 text-red-800 px-2 py-1 rounded">Not Notified</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Employee Overview Panel --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">👥 Employees</h2>
            @if($employees->isEmpty())
                <p class="text-gray-500 text-sm">No active employees. <a href="{{ route('employees.create') }}" class="text-blue-600 hover:underline">Add one.</a></p>
            @else
                <div class="space-y-3">
                    @foreach($employees as $employee)
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <div>
                                <a href="{{ route('employees.edit', $employee) }}" class="font-medium text-gray-800 hover:text-blue-600">{{ $employee->name }}</a>
                                <p class="text-xs text-gray-400">Scan cursor: PK {{ $employee->scan_start_primary_key }}</p>
                            </div>
                            @if($employee->is_active)
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">Active</span>
                            @else
                                <span class="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded">Inactive</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ═══ Recent Scan Activity ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">📋 Recent Scan Activity</h2>
        @if($recentScans->isEmpty())
            <p class="text-gray-500 text-sm">No scans have been run yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">Employee</th>
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">Status</th>
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">Scanned</th>
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">Collected</th>
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">Errors</th>
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentScans as $scan)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-2 px-3">{{ $scan->employee->name ?? 'Deleted' }}</td>
                                <td class="py-2 px-3">
                                    @if($scan->status === 'completed')
                                        <span class="text-green-600">✓ Completed</span>
                                    @elseif($scan->status === 'failed')
                                        <span class="text-red-600">✗ Failed</span>
                                    @else
                                        <span class="text-yellow-600">⟳ Running</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-right">{{ $scan->rows_scanned }}</td>
                                <td class="py-2 px-3 text-right">{{ $scan->rows_collected }}</td>
                                <td class="py-2 px-3 text-right {{ $scan->error_count > 0 ? 'text-red-600' : '' }}">{{ $scan->error_count }}</td>
                                <td class="py-2 px-3 text-gray-400">{{ $scan->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ═══ System Health ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">🖥️ System Health</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Last Scan</p>
                <p class="font-medium">{{ $systemHealth['last_scan'] instanceof \Carbon\Carbon ? $systemHealth['last_scan']->diffForHumans() : $systemHealth['last_scan'] }}</p>
            </div>
            <div>
                <p class="text-gray-500">Active Employees</p>
                <p class="font-medium">{{ $systemHealth['active_employees'] }}</p>
            </div>
            <div>
                <p class="text-gray-500">Active Clients</p>
                <p class="font-medium">{{ $systemHealth['active_clients'] }}</p>
            </div>
            <div>
                <p class="text-gray-500">PHP Version</p>
                <p class="font-medium">{{ $systemHealth['php_version'] }}</p>
            </div>
        </div>
    </div>

@endsection
