{{-- WHM Servers list with test/sync actions --}}
@extends('layouts.app')
@section('title', 'WHM Servers')
@section('header', 'WHM Servers')

@section('content')
<div class="max-w-4xl">
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-gray-500">Manage WHM/cPanel server connections.</p>
        <a href="{{ route('hosting.server.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ Add Server</a>
    </div>

    @if($servers->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No WHM servers configured yet.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($servers as $server)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-900">{{ $server->name }}</h3>
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $server->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $server->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">
                                <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">{{ $server->hostname }}:{{ $server->port }}</code>
                                &middot; {{ $server->auth_type }}
                                &middot; {{ $server->hosting_accounts_count }} accounts
                            </p>
                            @if($server->last_synced_at)
                                <p class="text-xs text-gray-400 mt-1">Last synced: {{ $server->last_synced_at->diffForHumans() }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('hosting.server.test', $server) }}">@csrf
                                <button type="submit" class="text-xs bg-gray-100 text-gray-700 px-3 py-1.5 rounded hover:bg-gray-200">Test</button>
                            </form>
                            <form method="POST" action="{{ route('hosting.server.sync', $server) }}">@csrf
                                <button type="submit" class="text-xs bg-green-50 text-green-700 px-3 py-1.5 rounded hover:bg-green-100">Sync</button>
                            </form>
                            <a href="{{ route('hosting.server-info', $server) }}" class="text-xs bg-blue-50 text-blue-700 px-3 py-1.5 rounded hover:bg-blue-100">Info</a>
                            <a href="{{ route('hosting.server.edit', $server) }}" class="text-xs bg-gray-100 text-gray-700 px-3 py-1.5 rounded hover:bg-gray-200">Edit</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
