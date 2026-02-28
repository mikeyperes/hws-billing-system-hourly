@extends('layouts.app')
@section('title', 'New Email Template')
@section('header', 'New Email Template')

@section('content')
<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('emails.store') }}">
            @csrf
            @include('emails._form', ['template' => null, 'useCases' => $useCases, 'shortcodes' => $shortcodes])
            <div class="flex items-center gap-3">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700">Create Template</button>
                <a href="{{ route('emails.index') }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
