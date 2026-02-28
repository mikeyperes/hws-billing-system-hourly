{{-- Cloud Services Overview --}}
@extends('layouts.app')
@section('title', 'Cloud Services')
@section('header', 'Cloud Services')

@section('content')
<div class="space-y-6">

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-2xl font-bold text-gray-900">{{ $activeAccounts }}</p>
            <p class="text-sm text-gray-500">Active Accounts</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-2xl font-bold text-gray-900">{{ $totalSubscriptions }}</p>
            <p class="text-sm text-gray-500">Active Subscriptions</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-2xl font-bold text-green-700">${{ number_format($monthlyRevenue / 100, 2) }}</p>
            <p class="text-sm text-gray-500">Monthly Revenue</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-2xl font-bold text-gray-900">{{ $servers->count() }}</p>
            <p class="text-sm text-gray-500">WHM Servers</p>
        </div>
    </div>

    {{-- Quick Links --}}
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('hosting.accounts') }}" class="bg-white border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 shadow-sm">All Accounts</a>
        <a href="{{ route('hosting.servers') }}" class="bg-white border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 shadow-sm">WHM Servers</a>
        <a href="{{ route('hosting.mapping-tool') }}" class="bg-white border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 shadow-sm">Subscription Mapping</a>
        <a href="{{ route('hosting.maintenance') }}" class="bg-white border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 shadow-sm">Server Maintenance</a>
        <form method="POST" action="{{ route('hosting.sync-all') }}" class="inline">@csrf
            <button class="bg-green-600 text-white rounded-lg px-4 py-2 text-sm hover:bg-green-700">Sync All Servers</button>
        </form>
    </div>

    {{-- Servers --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Servers</h2>
        @if($servers->isEmpty())
            <p class="text-sm text-gray-400 italic">No servers. <a href="{{ route('hosting.server.create') }}" class="text-blue-600 hover:underline">Add one</a>.</p>
        @else
            <div class="space-y-3">
                @foreach($servers as $server)
                    <div class="flex items-center justify-between border border-gray-100 rounded-lg p-3">
                        <div>
                            <span class="font-medium text-gray-900">{{ $server->name }}</span>
                            <span class="text-xs text-gray-500 ml-2">{{ $server->hostname }} &middot; {{ $server->hosting_accounts_count }} accounts</span>
                            <span class="text-xs px-1.5 py-0.5 rounded-full ml-2 {{ $server->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $server->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('hosting.server-info', $server) }}" class="text-xs text-blue-600 hover:underline">Info</a>
                            <a href="{{ route('hosting.server.edit', $server) }}" class="text-xs text-gray-500 hover:underline">Edit</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
