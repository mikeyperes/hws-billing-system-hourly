{{-- Email Templates: list grouped by use case --}}
@extends('layouts.app')

@section('title', 'Email Templates — ' . config('hws.app_name'))
@section('header', 'Email Templates')

@section('content')

    {{-- Top action bar --}}
    <div class="flex justify-between items-center mb-6">
        <p class="text-gray-500 text-sm">Templates organized by use case</p>
        <a href="{{ route('emails.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
            + New Template
        </a>
    </div>

    {{-- ═══ Templates by Use Case ═══ --}}
    @forelse($templates as $useCase => $useCaseTemplates)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
            {{-- Use case header --}}
            <h2 class="font-semibold text-gray-800 mb-3">{{ $useCase }}</h2>

            <div class="space-y-2">
                @foreach($useCaseTemplates as $template)
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        {{-- Template info --}}
                        <div>
                            <span class="font-medium text-gray-700">{{ $template->name }}</span>
                            @if($template->is_primary)
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded ml-2">Primary</span>
                            @endif
                            @if(!$template->is_active)
                                <span class="text-xs bg-gray-200 text-gray-500 px-2 py-1 rounded ml-2">Inactive</span>
                            @endif
                        </div>
                        {{-- Actions --}}
                        <div class="flex gap-2">
                            @if(!$template->is_primary)
                                <form method="POST" action="{{ route('emails.primary', $template) }}">
                                    @csrf
                                    <button type="submit" class="text-xs text-green-600 hover:text-green-800 px-2 py-1 bg-green-50 rounded">
                                        Set Primary
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('emails.edit', $template) }}" class="text-xs text-blue-600 hover:text-blue-800 px-2 py-1 bg-blue-50 rounded">
                                Edit
                            </a>
                            <form method="POST" action="{{ route('emails.destroy', $template) }}"
                                onsubmit="return confirm('Delete this template?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:text-red-800 px-2 py-1 bg-red-50 rounded">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center text-gray-500">
            No email templates. <a href="{{ route('emails.create') }}" class="text-blue-600 hover:underline">Create one.</a>
        </div>
    @endforelse

    {{-- ═══ Shortcode Reference ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
        <h2 class="font-semibold text-gray-800 mb-3">Shortcode Reference</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
            @foreach($shortcodes as $code => $description)
                <div class="flex gap-2">
                    <code class="bg-gray-100 px-2 py-1 rounded text-xs whitespace-nowrap">{{ $code }}</code>
                    <span class="text-gray-500">{{ $description }}</span>
                </div>
            @endforeach
        </div>
    </div>

@endsection
