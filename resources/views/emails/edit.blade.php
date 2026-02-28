@extends('layouts.app')
@section('title', 'Edit Template: ' . $template->name)
@section('header', 'Edit: ' . $template->name)

@section('content')
<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('emails.update', $template) }}">
            @csrf @method('PUT')
            @include('emails._form', ['template' => $template, 'useCases' => $useCases, 'shortcodes' => $shortcodes])
            <div class="flex items-center gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">Save Changes</button>
                <a href="{{ route('emails.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">Cancel</a>
            </div>
        </form>
    </div>

    {{-- Test send --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
        <h3 class="font-semibold text-gray-800 mb-3">Send Test Email</h3>
        <form method="POST" action="{{ route('emails.test', $template) }}" class="flex items-end gap-3">
            @csrf
            <div class="flex-1">
                <label class="block text-xs text-gray-500 mb-1">Test Email Address</label>
                <input type="email" name="test_email" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="you@example.com" required>
            </div>
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">Send Test</button>
        </form>
    </div>
</div>
@endsection
