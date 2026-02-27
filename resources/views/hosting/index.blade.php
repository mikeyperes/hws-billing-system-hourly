{{-- Hexa Cloud Services — overview dashboard --}}
@extends('layouts.app')
@section('title', 'Cloud Services')
@section('header', 'Hexa Cloud Services')

@section('content')
{{-- Stats cards --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Servers</p>
        <p class="text-2xl font-bold text-gray-800 mt-1">{{ $servers->count() }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Total Accounts</p>
        <p class="text-2xl font-bold text-gray-800 mt-1">{{ $totalAccounts }}</p>
        <p class="text-xs text-gray-400">{{ $activeAccounts }} active</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Active Subscriptions</p>
        <p class="text-2xl font-bold text-gray-800 mt-1">{{ $totalSubscriptions }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Monthly Revenue</p>
        <p class="text-2xl font-bold text-green-600 mt-1">${{ number_format($monthlyRevenue / 100, 2) }}</p>
    </div>
</div>

{{-- Server list --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900">WHM Servers</h2>
        <a href="{{ route('hosting.server.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
            + Add Server
        </a>
    </div>

    @if($servers->isEmpty())
        <p class="text-sm text-gray-400 italic">No servers configured yet. Add a WHM server to get started.</p>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-gray-600">Server</th>
                    <th class="px-4 py-2 text-left text-gray-600">Hostname</th>
                    <th class="px-4 py-2 text-center text-gray-600">Accounts</th>
                    <th class="px-4 py-2 text-center text-gray-600">Status</th>
                    <th class="px-4 py-2 text-left text-gray-600">Last Sync</th>
                    <th class="px-4 py-2 text-right text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($servers as $server)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $server->name }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $server->hostname }}:{{ $server->port }}</td>
                        <td class="px-4 py-3 text-center">{{ $server->hosting_accounts_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs {{ $server->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $server->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $server->last_synced_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('hosting.server.edit', $server) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- Quick links --}}
<div class="mt-6 flex gap-4">
    <a href="{{ route('hosting.accounts') }}" class="text-sm text-blue-600 hover:underline">View All Accounts →</a>
    <a href="{{ route('hosting.servers') }}" class="text-sm text-blue-600 hover:underline">Manage Servers →</a>
</div>
@endsection
