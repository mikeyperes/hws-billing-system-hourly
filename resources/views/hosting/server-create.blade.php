{{-- Add WHM Server form --}}
@extends('layouts.app')
@section('title', 'Add Server')
@section('header', 'Add WHM Server')

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('hosting.server.store') }}">
            @csrf

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Server Name</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Production VPS 1" required>
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label for="hostname" class="block text-sm font-medium text-gray-700 mb-1">Hostname / IP</label>
                <input type="text" name="hostname" id="hostname" value="{{ old('hostname') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="server.example.com" required>
                @error('hostname') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="port" class="block text-sm font-medium text-gray-700 mb-1">WHM Port</label>
                    <input type="number" name="port" id="port" value="{{ old('port', 2087) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" name="username" id="username" value="{{ old('username', 'root') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div class="mb-4">
                <label for="auth_type" class="block text-sm font-medium text-gray-700 mb-1">Auth Type</label>
                <select name="auth_type" id="auth_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="api_token" {{ old('auth_type') === 'api_token' ? 'selected' : '' }}>API Token</option>
                    <option value="root_password" {{ old('auth_type') === 'root_password' ? 'selected' : '' }}>Root Password</option>
                    <option value="access_hash" {{ old('auth_type') === 'access_hash' ? 'selected' : '' }}>Access Hash</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="credentials" class="block text-sm font-medium text-gray-700 mb-1">Credentials</label>
                <textarea name="credentials" id="credentials" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="API token, password, or access hash">{{ old('credentials') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">Stored encrypted. For API tokens, generate in WHM → Manage API Tokens.</p>
            </div>

            <div class="mb-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" id="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">Add Server</button>
                <a href="{{ route('hosting.servers') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
