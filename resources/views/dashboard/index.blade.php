{{-- Dashboard: main hub for Hexa Billing System --}}
{{-- 3 service sections: Hourly Billing, Cloud Services, Invoice Generator --}}
@extends('layouts.app')

@section('title', 'Dashboard — ' . config('hws.app_name'))
@section('header', 'Dashboard')

@section('content')

    {{-- ═══ SERVICE 1: Hourly Billing ═══ --}}
    <div class="mb-8">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h2 class="text-lg font-semibold text-gray-900">Hourly Billing</h2>
            <span class="text-xs text-gray-400">Track employee hours from Google Sheets → generate Stripe invoices</span>
        </div>

        {{-- Invoice summary cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wider">Draft</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $invoiceCounts['draft'] }}</p>
                <p class="text-xs text-gray-400">${{ number_format($invoiceAmounts['draft'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wider">Outstanding</p>
                <p class="text-2xl font-bold text-yellow-600 mt-1">{{ $invoiceCounts['sent'] }}</p>
                <p class="text-xs text-gray-400">${{ number_format($invoiceAmounts['sent'], 2) }} awaiting</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wider">Paid</p>
                <p class="text-2xl font-bold text-green-600 mt-1">{{ $invoiceCounts['paid'] }}</p>
                <p class="text-xs text-gray-400">${{ number_format($invoiceAmounts['paid'], 2) }} collected</p>
            </div>
        </div>

        {{-- Two-column: flags + employees --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Low Credit Flags --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Low Credit Clients</h3>
                @if($lowCreditClients->isEmpty())
                    <p class="text-gray-400 text-sm italic">None flagged.</p>
                @else
                    <div class="space-y-2">
                        @foreach($lowCreditClients as $client)
                            <div class="flex justify-between items-center p-2 bg-yellow-50 rounded-lg text-sm">
                                <a href="{{ route('clients.edit', $client) }}" class="font-medium text-gray-800 hover:text-blue-600">{{ $client->name }}</a>
                                <span class="text-xs text-yellow-700">{{ $client->credit_balance_hours }} hrs</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Employees --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Employees ({{ $employees->count() }})</h3>
                @if($employees->isEmpty())
                    <p class="text-gray-400 text-sm italic">No active employees. <a href="{{ route('employees.create') }}" class="text-blue-600 hover:underline">Add one.</a></p>
                @else
                    <div class="space-y-2">
                        @foreach($employees as $employee)
                            <div class="flex justify-between items-center p-2 bg-gray-50 rounded-lg text-sm">
                                <a href="{{ route('employees.edit', $employee) }}" class="font-medium text-gray-800 hover:text-blue-600">{{ $employee->name }}</a>
                                <span class="text-xs {{ $employee->is_active ? 'text-green-600' : 'text-gray-400' }}">
                                    {{ $employee->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent scans --}}
        @if($recentScans->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Recent Scans</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-1.5 px-2 text-gray-500 font-medium text-xs">Employee</th>
                            <th class="text-left py-1.5 px-2 text-gray-500 font-medium text-xs">Status</th>
                            <th class="text-right py-1.5 px-2 text-gray-500 font-medium text-xs">Rows</th>
                            <th class="text-left py-1.5 px-2 text-gray-500 font-medium text-xs">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentScans->take(5) as $scan)
                            <tr class="border-b border-gray-100">
                                <td class="py-1.5 px-2">{{ $scan->employee->name ?? 'Deleted' }}</td>
                                <td class="py-1.5 px-2">
                                    <span class="{{ $scan->status === 'completed' ? 'text-green-600' : ($scan->status === 'failed' ? 'text-red-600' : 'text-yellow-600') }}">
                                        {{ ucfirst($scan->status) }}
                                    </span>
                                </td>
                                <td class="py-1.5 px-2 text-right">{{ $scan->rows_collected }}</td>
                                <td class="py-1.5 px-2 text-gray-400 text-xs">{{ $scan->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="flex gap-3 mt-3">
            <a href="{{ route('scan.index') }}" class="text-sm text-blue-600 hover:underline">Run Scan →</a>
            <a href="{{ route('invoices.index') }}" class="text-sm text-blue-600 hover:underline">View Invoices →</a>
        </div>
    </div>

    {{-- ═══ SERVICE 2: Cloud Services ═══ --}}
    <div class="mb-8 pt-6 border-t border-gray-200">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
            </svg>
            <h2 class="text-lg font-semibold text-gray-900">Hexa Cloud Services</h2>
            <span class="text-xs text-gray-400">WHM servers → hosting accounts → Stripe subscriptions</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wider">Servers</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $cloudStats['servers'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wider">Accounts</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $cloudStats['total_accounts'] }}</p>
                <p class="text-xs text-gray-400">{{ $cloudStats['active_accounts'] }} active</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wider">Subscriptions</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $cloudStats['active_subscriptions'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wider">Monthly Revenue</p>
                <p class="text-2xl font-bold text-green-600 mt-1">${{ number_format($cloudStats['monthly_revenue'] / 100, 2) }}</p>
            </div>
        </div>

        <div class="flex gap-3 mt-3">
            <a href="{{ route('hosting.index') }}" class="text-sm text-blue-600 hover:underline">Cloud Overview →</a>
            <a href="{{ route('hosting.servers') }}" class="text-sm text-blue-600 hover:underline">Manage Servers →</a>
            <a href="{{ route('hosting.accounts') }}" class="text-sm text-blue-600 hover:underline">View Accounts →</a>
        </div>
    </div>

    {{-- ═══ SERVICE 3: Invoice Generator ═══ --}}
    <div class="mb-8 pt-6 border-t border-gray-200">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <h2 class="text-lg font-semibold text-gray-900">Invoice Generator</h2>
            <span class="text-xs text-gray-400">Quick Stripe customer/subscription ID lookup for invoice creation</span>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-sm text-gray-600 mb-3">Enter client and amount parameters → get formatted Stripe IDs ready for invoice creation. Useful for one-off invoices outside the hourly billing flow.</p>
            <a href="{{ route('invoice-generator.index') }}" class="inline-block bg-amber-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-amber-700">
                Open Invoice Generator →
            </a>
        </div>
    </div>

    {{-- ═══ System Health ═══ --}}
    <div class="pt-6 border-t border-gray-200">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">System Health</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-gray-500 text-xs">Last Scan</p>
                    <p class="font-medium">{{ $systemHealth['last_scan'] instanceof \Carbon\Carbon ? $systemHealth['last_scan']->diffForHumans() : $systemHealth['last_scan'] }}</p>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Active Employees</p>
                    <p class="font-medium">{{ $systemHealth['active_employees'] }}</p>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Active Clients</p>
                    <p class="font-medium">{{ $systemHealth['active_clients'] }}</p>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">PHP / Version</p>
                    <p class="font-medium">{{ $systemHealth['php_version'] }} / v{{ config('hws.version') }}</p>
                </div>
            </div>
        </div>
    </div>

@endsection
