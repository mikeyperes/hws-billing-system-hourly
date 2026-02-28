{{-- Server Maintenance — run scripts, view output --}}
@extends('layouts.app')
@section('title', 'Server Maintenance')
@section('header', 'Server Maintenance')

@section('content')
<div class="space-y-6">

    {{-- Script Runner --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Run Script</h2>
        <form method="POST" action="{{ route('hosting.maintenance.run') }}" class="flex flex-wrap items-end gap-3" id="script-form">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">Server</label>
                <select name="server_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    <option value="">Select server...</option>
                    @foreach($servers as $s)
                        <option value="{{ $s->id }}" {{ isset($executedServer) && $executedServer->id == $s->id ? 'selected' : '' }}>{{ $s->name }} ({{ $s->hostname }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[250px]">
                <label class="block text-xs text-gray-500 mb-1">Script</label>
                <select name="script_id" id="script-select" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    <option value="">Select script...</option>
                    @foreach($scriptsByCategory as $category => $scripts)
                        <optgroup label="{{ ucfirst($category) }}">
                            @foreach($scripts as $script)
                                <option value="{{ $script->id }}"
                                    data-danger="{{ $script->danger_level }}"
                                    data-desc="{{ $script->description }}"
                                    data-cmd="{{ $script->command }}"
                                    {{ isset($executedScript) && $executedScript->id == $script->id ? 'selected' : '' }}>
                                    {{ $script->name }}
                                    @if($script->danger_level !== 'safe') [{{ strtoupper($script->danger_level) }}] @endif
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <button type="submit" id="run-btn" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">Run</button>
        </form>

        {{-- Script detail preview --}}
        <div id="script-preview" class="mt-3 hidden">
            <div class="bg-gray-50 rounded-lg p-3 text-sm">
                <p id="script-desc" class="text-gray-600 mb-1"></p>
                <code id="script-cmd" class="text-xs bg-gray-200 px-2 py-1 rounded block text-gray-700"></code>
                <div id="danger-warning" class="mt-2 text-xs font-medium hidden"></div>
            </div>
        </div>
    </div>

    {{-- Execution Result --}}
    @if(isset($scriptResult))
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-gray-900">
                    Result: {{ $executedScript->name }}
                    <span class="text-sm font-normal text-gray-500">on {{ $executedServer->name }}</span>
                </h2>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $scriptResult['success'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ $scriptResult['success'] ? 'Success' : 'Failed' }}
                </span>
            </div>
            <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                <pre class="text-xs text-gray-100 whitespace-pre-wrap font-mono">{{ $scriptResult['output'] ?? $scriptResult['error'] ?? 'No output.' }}</pre>
            </div>
        </div>
    @endif

    {{-- Available Scripts Library --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Script Library</h2>
        @foreach($scriptsByCategory as $category => $scripts)
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">{{ ucfirst($category) }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach($scripts as $script)
                        <div class="border border-gray-100 rounded-lg p-3 flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-sm text-gray-900">{{ $script->name }}</span>
                                    <span class="text-xs px-1.5 py-0.5 rounded-full {{ $script->danger_badge }}">{{ ucfirst($script->danger_level) }}</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $script->description }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
document.getElementById('script-select').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const preview = document.getElementById('script-preview');
    const btn = document.getElementById('run-btn');
    if (!opt.value) { preview.classList.add('hidden'); return; }
    preview.classList.remove('hidden');
    document.getElementById('script-desc').textContent = opt.dataset.desc;
    document.getElementById('script-cmd').textContent = opt.dataset.cmd;
    const warn = document.getElementById('danger-warning');
    const danger = opt.dataset.danger;
    if (danger === 'destructive') {
        warn.textContent = 'DESTRUCTIVE — This action cannot be undone.';
        warn.className = 'mt-2 text-xs font-medium text-red-600';
        warn.classList.remove('hidden');
        btn.className = 'bg-red-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-red-700';
    } else if (danger === 'caution') {
        warn.textContent = 'CAUTION — This will modify files on the server.';
        warn.className = 'mt-2 text-xs font-medium text-yellow-600';
        warn.classList.remove('hidden');
        btn.className = 'bg-yellow-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-yellow-700';
    } else {
        warn.classList.add('hidden');
        btn.className = 'bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700';
    }
});
document.getElementById('script-form').addEventListener('submit', function(e) {
    const opt = document.getElementById('script-select').options[document.getElementById('script-select').selectedIndex];
    if (opt.dataset.danger === 'destructive' && !confirm('This is a DESTRUCTIVE action. Are you sure?')) e.preventDefault();
});
</script>
@endsection
