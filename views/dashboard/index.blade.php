{{-- Dashboard — service hub overview --}}
@extends('layouts.app')
@section('title', 'Dashboard')
@section('header', 'Dashboard')

@section('content')
<div class="space-y-6">

    {{-- ── Service Status (Stripe + Brevo) ── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Stripe Status --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900">Stripe</h3>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $serviceStatus['stripe']['configured'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ $serviceStatus['stripe']['configured'] ? 'Connected' : 'Not Configured' }}
                </span>
            </div>
            @if(count($serviceStatus['stripe']['accounts']) > 0)
                <div class="space-y-1">
                    @foreach($serviceStatus['stripe']['accounts'] as $acct)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-700">{{ $acct['name'] }}</span>
                            <div class="flex items-center gap-2">
                                <code class="text-xs text-gray-400">{{ $acct['masked_key'] }}</code>
                                @if($acct['is_default'])
                                    <span class="text-xs bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded">Default</span>
                                @endif
                                <span class="w-2 h-2 rounded-full {{ $acct['is_active'] ? 'bg-green-400' : 'bg-gray-300' }}"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif($serviceStatus['stripe']['has_env_key'])
                <p class="text-sm text-gray-500">Using .env STRIPE_SECRET_KEY (legacy).</p>
            @else
                <p class="text-sm text-gray-400">No Stripe accounts configured. <a href="{{ route('settings.stripe-accounts.index') }}" class="text-blue-600 hover:underline">Add one</a></p>
            @endif
        </div>

        {{-- Brevo/SMTP Status --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900">Brevo (SMTP)</h3>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $serviceStatus['brevo']['configured'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ $serviceStatus['brevo']['configured'] ? 'Configured' : 'Not Configured' }}
                </span>
            </div>
            @if($serviceStatus['brevo']['configured'])
                <div class="text-sm space-y-1 text-gray-600">
                    <p>Host: <code class="text-xs bg-gray-50 px-1 rounded">{{ $serviceStatus['brevo']['host'] }}:{{ $serviceStatus['brevo']['port'] }}</code></p>
                    <p>From: <code class="text-xs bg-gray-50 px-1 rounded">{{ $serviceStatus['brevo']['from'] }}</code></p>
                </div>
            @else
                <p class="text-sm text-gray-400">SMTP not configured. Check .env mail settings.</p>
            @endif
        </div>
    </div>

    {{-- ── Hourly Billing Module ── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-900">Hourly Billing</h3>
            <a href="{{ route('scan.index') }}" class="text-sm text-blue-600 hover:underline">Run Scan →</a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-2xl font-bold text-gray-900">{{ $invoiceCounts['draft'] ?? 0 }}</p>
                <p class="text-xs text-gray-500">Draft Invoices</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-yellow-600">{{ $invoiceCounts['sent'] ?? 0 }}</p>
                <p class="text-xs text-gray-500">Outstanding</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-green-600">{{ $invoiceCounts['paid'] ?? 0 }}</p>
                <p class="text-xs text-gray-500">Paid</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-red-600">{{ count($lowCreditClients) }}</p>
                <p class="text-xs text-gray-500">Low Credit Clients</p>
            </div>
        </div>
    </div>

    {{-- ── Cloud Services Module ── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-900">Cloud Services</h3>
            <a href="{{ route('hosting.index') }}" class="text-sm text-blue-600 hover:underline">Overview →</a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-2xl font-bold text-gray-900">{{ $cloudStats['servers'] }}</p>
                <p class="text-xs text-gray-500">WHM Servers</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900">{{ $cloudStats['active_accounts'] }}</p>
                <p class="text-xs text-gray-500">Active Accounts</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900">{{ $cloudStats['active_subscriptions'] }}</p>
                <p class="text-xs text-gray-500">Subscriptions</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-green-600">${{ number_format($cloudStats['monthly_revenue'] / 100, 2) }}</p>
                <p class="text-xs text-gray-500">Monthly Revenue</p>
            </div>
        </div>
    </div>

    {{-- ── Employees + Recent Scans ── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Employees --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-900">Active Employees ({{ $employees->count() }})</h3>
                <a href="{{ route('employees.index') }}" class="text-sm text-blue-600 hover:underline">Manage →</a>
            </div>
            @if($employees->isEmpty())
                <p class="text-sm text-gray-400 italic">No active employees.</p>
            @else
                <div class="space-y-2">
                    @foreach($employees->take(10) as $emp)
                        <div class="flex items-center justify-between text-sm">
                            <a href="{{ route('employees.edit', $emp) }}" class="text-gray-700 hover:text-blue-600">{{ $emp->name }}</a>
                            <span class="text-xs text-gray-400">Cursor: {{ $emp->scan_start_primary_key }}</span>
                        </div>
                    @endforeach
                    @if($employees->count() > 10)
                        <p class="text-xs text-gray-400">+{{ $employees->count() - 10 }} more</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Recent Scans --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900 mb-4">Recent Scans</h3>
            @if($recentScans->isEmpty())
                <p class="text-sm text-gray-400 italic">No scans recorded yet.</p>
            @else
                <div class="space-y-2">
                    @foreach($recentScans->take(5) as $scan)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-700">{{ $scan->employee->name ?? 'Unknown' }}</span>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400">{{ $scan->created_at->diffForHumans() }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded
                                    {{ $scan->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $scan->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}">
                                    {{ ucfirst($scan->status) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ── System Health ── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-900 mb-3">System</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Last Scan</p>
                <p class="text-gray-900">{{ $systemHealth['last_scan'] instanceof \Carbon\Carbon ? $systemHealth['last_scan']->diffForHumans() : $systemHealth['last_scan'] }}</p>
            </div>
            <div>
                <p class="text-gray-500">Active Employees</p>
                <p class="text-gray-900">{{ $systemHealth['active_employees'] }}</p>
            </div>
            <div>
                <p class="text-gray-500">Active Clients</p>
                <p class="text-gray-900">{{ $systemHealth['active_clients'] }}</p>
            </div>
            <div>
                <p class="text-gray-500">Version</p>
                <p class="text-gray-900">{{ config('hws.version') }}</p>
            </div>
        </div>
    </div>

</div>
@endsection
