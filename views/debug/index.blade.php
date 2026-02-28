{{-- Debug modules index — lists all available debug modules --}}
@extends('layouts.app')
@section('title', 'Debug Modules')
@section('header', 'Debug Modules')

@section('content')
<div class="mb-6">
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg text-sm">
        Debug mode is <strong>ON</strong>. These tools are for testing and troubleshooting system integrations.
        Disable by setting <code>HWS_DEBUG_MODE=false</code> in your <code>.env</code> file.
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    @foreach($modules as $module)
        <a href="{{ route($module['route']) }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-start gap-4">
                {{-- Module icon --}}
                <div class="bg-gray-100 rounded-lg p-3">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $module['icon'] }}"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">{{ $module['name'] }}</h3>
                        {{-- Status badge --}}
                        @if($module['status'] === 'ready')
                            <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">Ready</span>
                        @else
                            <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full">Needs Setup</span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-500 mt-1">{{ $module['desc'] }}</p>
                </div>
            </div>
        </a>
    @endforeach
</div>
@endsection
