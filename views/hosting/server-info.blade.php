{{-- Server Info — detailed specs pulled from WHM API --}}
@extends('layouts.app')
@section('title', 'Server Info: ' . $server->name)
@section('header', 'Server Info: ' . $server->name)

@section('content')
<div class="max-w-3xl space-y-6">

    @if($error)
        <div class="bg-red-50 text-red-700 rounded-lg p-4 text-sm">{{ $error }}</div>
    @endif

    {{-- Server identity --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Server Identity</h2>
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-gray-500">Display Name</dt><dd class="font-medium">{{ $server->name }}</dd></div>
            <div><dt class="text-gray-500">Connection</dt><dd class="font-mono text-xs">{{ $server->hostname }}:{{ $server->port }}</dd></div>
            @if(!empty($info['hostname']))
                <div><dt class="text-gray-500">Reported Hostname</dt><dd class="font-mono text-xs">{{ $info['hostname'] }}</dd></div>
            @endif
            @if(!empty($info['whm_version']))
                <div><dt class="text-gray-500">WHM/cPanel Version</dt><dd>{{ $info['whm_version'] }}</dd></div>
            @endif
            <div><dt class="text-gray-500">Auth Method</dt><dd>{{ $server->auth_type }}</dd></div>
            <div><dt class="text-gray-500">Account Count</dt><dd>{{ $server->account_count }}</dd></div>
            @if($server->last_synced_at)
                <div><dt class="text-gray-500">Last Synced</dt><dd>{{ $server->last_synced_at->format('M j, Y g:i A') }} ({{ $server->last_synced_at->diffForHumans() }})</dd></div>
            @endif
        </dl>
    </div>

    {{-- Load Averages --}}
    @if(!empty($info['load_1']))
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">System Load</h2>
            <div class="grid grid-cols-3 gap-4">
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ $info['load_1'] }}</p>
                    <p class="text-xs text-gray-500">1 min</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ $info['load_5'] }}</p>
                    <p class="text-xs text-gray-500">5 min</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ $info['load_15'] }}</p>
                    <p class="text-xs text-gray-500">15 min</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Disk Partitions --}}
    @if(!empty($info['disk_partitions']))
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Disk Usage</h2>
            <div class="space-y-3">
                @foreach($info['disk_partitions'] as $part)
                    @php
                        $used = $part['used'] ?? ($part['blocks_used'] ?? 0);
                        $total = $part['total'] ?? ($part['blocks'] ?? 1);
                        $pct = $total > 0 ? round(($used / $total) * 100, 1) : 0;
                        $mount = $part['mount'] ?? ($part['filesystem'] ?? 'Unknown');
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-mono text-xs text-gray-600">{{ $mount }}</span>
                            <span class="text-gray-500">{{ $pct }}%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="h-2 rounded-full {{ $pct > 90 ? 'bg-red-500' : ($pct > 70 ? 'bg-yellow-500' : 'bg-green-500') }}"
                                style="width: {{ min($pct, 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Quick actions --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('hosting.maintenance') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Run Maintenance Scripts</a>
        <form method="POST" action="{{ route('hosting.server.sync', $server) }}">@csrf
            <button class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">Sync Accounts</button>
        </form>
        <a href="{{ route('hosting.servers') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Back to Servers</a>
    </div>
</div>
@endsection
