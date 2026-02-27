{{-- Edit WHM Server form --}}
@extends('layouts.app')
@section('title', 'Edit Server')
@section('header', 'Edit Server: ' . $server->name)

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('hosting.server.update', $server) }}">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Server Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $server->name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            </div>

            <div class="mb-4">
                <label for="hostname" class="block text-sm font-medium text-gray-700 mb-1">Hostname / IP</label>
                <input type="text" name="hostname" id="hostname" value="{{ old('hostname', $server->hostname) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="port" class="block text-sm font-medium text-gray-700 mb-1">WHM Port</label>
                    <input type="number" name="port" id="port" value="{{ old('port', $server->port) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" name="username" id="username" value="{{ old('username', $server->username) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div class="mb-4">
                <label for="auth_type" class="block text-sm font-medium text-gray-700 mb-1">Auth Type</label>
                <select name="auth_type" id="auth_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="api_token" {{ $server->auth_type === 'api_token' ? 'selected' : '' }}>API Token</option>
                    <option value="root_password" {{ $server->auth_type === 'root_password' ? 'selected' : '' }}>Root Password</option>
                    <option value="access_hash" {{ $server->auth_type === 'access_hash' ? 'selected' : '' }}>Access Hash</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="credentials" class="block text-sm font-medium text-gray-700 mb-1">Credentials</label>
                <textarea name="credentials" id="credentials" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Leave blank to keep existing">{{ old('credentials') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">Leave blank to keep current credentials. Stored encrypted.</p>
            </div>

            <div class="mb-4">
                <label class="inline-flex items-center">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ $server->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                    <span class="ml-2 text-sm text-gray-700">Active</span>
                </label>
            </div>

            <div class="mb-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" id="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $server->notes) }}</textarea>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">Save Changes</button>
                <a href="{{ route('hosting.servers') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
