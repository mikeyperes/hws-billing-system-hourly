{{-- Email Templates: Template Center --}}
@extends('layouts.app')
@section('title', 'Template Center')
@section('header', 'Template Center')

@section('content')

    <div class="flex justify-between items-center mb-6">
        <p class="text-gray-500 text-sm">Email templates organized by use case. Each field supports shortcodes.</p>
        <a href="{{ route('emails.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ New Template</a>
    </div>

    @php
        // Map use_cases to where they're used and which shortcodes are relevant
        $useCaseInfo = [
            'invoice_notification' => [
                'used_in' => 'Invoice Email Center — sent after invoice creation',
                'shortcodes' => ['{{client_name}}','{{client_email}}','{{invoice_total}}','{{invoice_hours}}','{{invoice_date}}','{{invoice_stripe_url}}','{{work_log}}','{{credit_balance}}','{{company_name}}'],
            ],
            'low_credit_alert' => [
                'used_in' => 'Automatic low credit notifications',
                'shortcodes' => ['{{client_name}}','{{client_email}}','{{credit_balance}}','{{company_name}}'],
            ],
        ];
    @endphp

    @forelse($templates as $useCase => $useCaseTemplates)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-semibold text-gray-800">{{ $useCase }}</h2>
                <span class="text-xs text-gray-400">{{ $useCaseTemplates->count() }} template{{ $useCaseTemplates->count() !== 1 ? 's' : '' }}</span>
            </div>

            @if(isset($useCaseInfo[$useCase]))
                <p class="text-xs text-gray-500 mb-2">Used in: {{ $useCaseInfo[$useCase]['used_in'] }}</p>
                <div class="mb-3 p-2 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 mb-1">Available shortcodes:</p>
                    <div class="flex flex-wrap gap-1">
                        @foreach($useCaseInfo[$useCase]['shortcodes'] as $code)
                            <code class="text-xs bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded font-mono">{{ $code }}</code>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="space-y-2">
                @foreach($useCaseTemplates as $template)
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <div>
                            <span class="font-medium text-gray-700">{{ $template->name }}</span>
                            @if($template->is_primary)
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full ml-1">Primary</span>
                            @endif
                            @if(!$template->is_active)
                                <span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full ml-1">Inactive</span>
                            @endif
                            @if($template->subject)
                                <p class="text-xs text-gray-400 mt-0.5">Subject: {{ \Illuminate\Support\Str::limit($template->subject, 60) }}</p>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            @if(!$template->is_primary)
                                <form method="POST" action="{{ route('emails.primary', $template) }}">@csrf
                                    <button type="submit" class="text-xs text-green-600 hover:text-green-800 px-2 py-1 bg-green-50 rounded">Set Primary</button>
                                </form>
                            @endif
                            <a href="{{ route('emails.edit', $template) }}" class="text-xs text-blue-600 hover:text-blue-800 px-2 py-1 bg-blue-50 rounded">Edit</a>
                            <form method="POST" action="{{ route('emails.destroy', $template) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:text-red-800 px-2 py-1 bg-red-50 rounded">Delete</button>
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

    {{-- Full Shortcode Reference --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
        <h2 class="font-semibold text-gray-800 mb-3">All Email Shortcodes</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
            @foreach($shortcodes as $code => $description)
                <div class="flex gap-2 items-center">
                    <code class="bg-gray-100 px-2 py-1 rounded text-xs whitespace-nowrap font-mono">{{ $code }}</code>
                    <span class="text-gray-500 text-xs">{{ $description }}</span>
                </div>
            @endforeach
        </div>
    </div>

@endsection
