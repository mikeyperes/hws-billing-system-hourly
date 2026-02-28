{{-- Server create/edit form (shared) --}}
@extends('layouts.app')
@section('title', $server ? 'Edit Server' : 'Add Server')
@section('header', $server ? 'Edit Server: ' . $server->name : 'Add WHM Server')

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ $server ? route('hosting.server.update', $server) : route('hosting.server.store') }}">
            @csrf
            @if($server) @method('PUT') @endif

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Server Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $server->name ?? '') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required autofocus
                    placeholder="Production VPS 1">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="col-span-2">
                    <label for="hostname" class="block text-sm font-medium text-gray-700 mb-1">Hostname / IP</label>
                    <input type="text" name="hostname" id="hostname" value="{{ old('hostname', $server->hostname ?? '') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required
                        placeholder="server.example.com">
                </div>
                <div>
                    <label for="port" class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                    <input type="number" name="port" id="port" value="{{ old('port', $server->port ?? 2087) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label for="auth_type" class="block text-sm font-medium text-gray-700 mb-1">Auth Method</label>
                    <select name="auth_type" id="auth_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="api_token" {{ old('auth_type', $server->auth_type ?? '') === 'api_token' ? 'selected' : '' }}>API Token (recommended)</option>
                        <option value="access_hash" {{ old('auth_type', $server->auth_type ?? '') === 'access_hash' ? 'selected' : '' }}>Access Hash</option>
                        <option value="root_password" {{ old('auth_type', $server->auth_type ?? '') === 'root_password' ? 'selected' : '' }}>Root Password</option>
                    </select>
                </div>
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" name="username" id="username" value="{{ old('username', $server->username ?? 'root') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div class="mb-4">
                <label for="credentials" class="block text-sm font-medium text-gray-700 mb-1">
                    Credentials @if($server) <span class="text-gray-400 font-normal">(leave blank to keep current)</span> @endif
                </label>
                <textarea name="credentials" id="credentials" rows="2"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                    placeholder="Paste API token, access hash, or password"
                    {{ $server ? '' : 'required' }}>{{ old('credentials') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">Encrypted at rest. Never displayed in plain text.</p>
            </div>

            <div class="mb-4">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" id="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $server->notes ?? '') }}</textarea>
            </div>

            @if($server)
                <div class="mb-6">
                    <label class="inline-flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" {{ $server->is_active ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600">
                        <span class="ml-2 text-sm text-gray-700">Active</span>
                    </label>
                </div>
            @endif

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">
                    {{ $server ? 'Save Changes' : 'Add Server' }}
                </button>
                <a href="{{ route('hosting.servers') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
