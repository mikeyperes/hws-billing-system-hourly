{{-- WHM Servers management page --}}
@extends('layouts.app')
@section('title', 'WHM Servers')
@section('header', 'WHM Servers')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900">All Servers</h2>
        <a href="{{ route('hosting.server.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
            + Add Server
        </a>
    </div>

    @if($servers->isEmpty())
        <p class="text-sm text-gray-400 italic">No servers configured.</p>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-gray-600">Name</th>
                    <th class="px-4 py-2 text-left text-gray-600">Hostname</th>
                    <th class="px-4 py-2 text-left text-gray-600">Auth</th>
                    <th class="px-4 py-2 text-center text-gray-600">Accounts</th>
                    <th class="px-4 py-2 text-center text-gray-600">Active</th>
                    <th class="px-4 py-2 text-right text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($servers as $server)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $server->name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $server->hostname }}:{{ $server->port }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $server->auth_type }}</td>
                        <td class="px-4 py-3 text-center">{{ $server->hosting_accounts_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs {{ $server->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $server->is_active ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('hosting.server.edit', $server) }}" class="text-blue-600 hover:underline text-sm">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
