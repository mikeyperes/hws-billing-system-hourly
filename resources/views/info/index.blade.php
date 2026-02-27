{{-- System Info page — version, git details, server info, command reference --}}
@extends('layouts.app')
@section('title', 'System Info')
@section('header', 'System Info')

@section('content')
<div class="space-y-6">

    {{-- ── Version + Server Overview ── --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        {{-- HWS Version --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wider">HWS Version</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">v{{ $serverInfo['hws_version'] }}</p>
        </div>
        {{-- Laravel Version --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Laravel</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $serverInfo['laravel_version'] }}</p>
        </div>
        {{-- PHP Version --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wider">PHP</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $serverInfo['php_version'] }}</p>
            <p class="text-xs text-gray-400">{{ $serverInfo['php_sapi'] }}</p>
        </div>
        {{-- Environment --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Environment</p>
            <p class="text-2xl font-bold mt-1 {{ $serverInfo['environment'] === 'production' ? 'text-green-600' : 'text-yellow-600' }}">
                {{ ucfirst($serverInfo['environment']) }}
            </p>
            <p class="text-xs text-gray-400">Debug: {{ $serverInfo['debug_mode'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- ── Git Information ── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Git Repository</h2>

            @if(isset($gitInfo['error']))
                <p class="text-red-500 text-sm">{{ $gitInfo['error'] }}</p>
            @else
                <table class="w-full text-sm">
                    <tbody>
                        <tr class="border-b border-gray-100">
                            <td class="py-2 text-gray-500 w-40">Branch</td>
                            <td class="py-2 font-mono text-gray-900">{{ $gitInfo['branch'] }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="py-2 text-gray-500">Latest Commit</td>
                            <td class="py-2 font-mono text-gray-900">{{ $gitInfo['commit_short'] }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="py-2 text-gray-500">Commit Message</td>
                            <td class="py-2 text-gray-900">{{ $gitInfo['commit_message'] }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="py-2 text-gray-500">Commit Date</td>
                            <td class="py-2 text-gray-900">{{ $gitInfo['commit_date'] }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="py-2 text-gray-500">Author</td>
                            <td class="py-2 text-gray-900">{{ $gitInfo['commit_author'] }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="py-2 text-gray-500">Total Commits</td>
                            <td class="py-2 text-gray-900">{{ $gitInfo['commit_count'] }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="py-2 text-gray-500">Remote</td>
                            <td class="py-2 text-gray-900 break-all text-xs">
                                @php
                                    // Strip token from remote URL for display
                                    $displayUrl = preg_replace('/\/\/[^@]+@/', '//', $gitInfo['remote_url']);
                                @endphp
                                <a href="{{ $displayUrl }}" target="_blank" class="text-blue-600 hover:underline">{{ $displayUrl }}</a>
                            </td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="py-2 text-gray-500">Last Fetch</td>
                            <td class="py-2 text-gray-900">{{ $gitInfo['last_fetch'] }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="py-2 text-gray-500">Working Tree</td>
                            <td class="py-2">
                                @if(empty($gitInfo['status']))
                                    <span class="text-green-600 font-medium">Clean</span>
                                @else
                                    <span class="text-yellow-600 font-medium">Modified</span>
                                    <pre class="mt-1 text-xs text-gray-500 bg-gray-50 p-2 rounded">{{ $gitInfo['status'] }}</pre>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>

                {{-- Recent Commits --}}
                @if($gitInfo['recent_commits'])
                    <h3 class="text-sm font-semibold text-gray-700 mt-4 mb-2">Recent Commits</h3>
                    <pre class="text-xs text-gray-600 bg-gray-50 p-3 rounded-lg overflow-x-auto max-h-48 overflow-y-auto">{{ $gitInfo['recent_commits'] }}</pre>
                @endif

                {{-- Tags --}}
                @if($gitInfo['tags'])
                    <h3 class="text-sm font-semibold text-gray-700 mt-4 mb-2">Tags</h3>
                    <pre class="text-xs text-gray-600 bg-gray-50 p-3 rounded-lg">{{ $gitInfo['tags'] }}</pre>
                @endif
            @endif
        </div>

        {{-- ── Server Details ── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Server Details</h2>
            <table class="w-full text-sm">
                <tbody>
                    <tr class="border-b border-gray-100">
                        <td class="py-2 text-gray-500 w-40">Hostname</td>
                        <td class="py-2 text-gray-900">{{ $serverInfo['hostname'] }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="py-2 text-gray-500">OS</td>
                        <td class="py-2 text-gray-900">{{ $serverInfo['os'] }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="py-2 text-gray-500">Web Server</td>
                        <td class="py-2 text-gray-900">{{ $serverInfo['server_software'] }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="py-2 text-gray-500">PHP SAPI</td>
                        <td class="py-2 text-gray-900">{{ $serverInfo['php_sapi'] }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="py-2 text-gray-500">Document Root</td>
                        <td class="py-2 text-gray-900 text-xs break-all">{{ $serverInfo['document_root'] }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="py-2 text-gray-500">Timezone</td>
                        <td class="py-2 text-gray-900">{{ $serverInfo['timezone'] }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="py-2 text-gray-500">Debug Mode</td>
                        <td class="py-2">
                            <span class="{{ $serverInfo['debug_mode'] === 'ON' ? 'text-yellow-600' : 'text-green-600' }} font-medium">
                                {{ $serverInfo['debug_mode'] }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            {{-- Debug page link --}}
            <div class="mt-4 pt-4 border-t border-gray-100">
                <a href="/debug.php" target="_blank" class="text-blue-600 hover:underline text-sm">
                    Open Full Debug Page (PHP extensions, DB test, connectivity) ↗
                </a>
            </div>
        </div>

    </div>

    {{-- ── Command Reference ── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Command Reference</h2>
        <p class="text-sm text-gray-500 mb-4">Run these commands via SSH as root on the server.</p>

        <div class="space-y-6">
            @foreach($commands as $group => $cmds)
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wider">{{ $group }}</h3>
                    <div class="space-y-2">
                        @foreach($cmds as $cmd)
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900">{{ $cmd['name'] }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $cmd['desc'] }}</p>
                                    </div>
                                </div>
                                @if(str_starts_with($cmd['command'], 'http'))
                                    <a href="{{ $cmd['command'] }}" target="_blank" class="block mt-2 text-xs text-blue-600 hover:underline">{{ $cmd['command'] }}</a>
                                @else
                                    <div class="mt-2 flex items-center gap-2">
                                        <code class="flex-1 text-xs bg-gray-900 text-green-400 px-3 py-2 rounded font-mono overflow-x-auto">{{ $cmd['command'] }}</code>
                                        <button onclick="navigator.clipboard.writeText('{{ addslashes($cmd['command']) }}')" class="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded hover:bg-gray-300 whitespace-nowrap">
                                            Copy
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
