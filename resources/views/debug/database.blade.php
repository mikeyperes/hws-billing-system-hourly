{{-- Debug: Database module --}}
@extends('layouts.app')
@section('title', 'Debug: Database')
@section('header', 'Debug: Database')

@section('content')
<div class="max-w-3xl space-y-6">
    <a href="{{ route('debug.index') }}" class="text-sm text-blue-600 hover:underline">← Back to Debug Modules</a>

    {{-- Test Connection --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Test Connection</h2>
        <p class="text-sm text-gray-500 mb-4">Tests the MySQL database connection and lists all tables with row counts.</p>
        <form method="POST" action="{{ route('debug.database') }}">
            @csrf
            <input type="hidden" name="action" value="test_connection">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                Test Connection
            </button>
        </form>
    </div>

    {{-- Results --}}
    @include('debug._results', ['results' => $results])

    {{-- Table details --}}
    @if(!empty($tables))
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Tables ({{ count($tables) }})</h2>
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-600">Table</th>
                        <th class="px-4 py-2 text-right text-gray-600">Rows</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tables as $table)
                        <tr class="border-t border-gray-100">
                            <td class="px-4 py-2 font-mono text-gray-900">{{ $table['name'] }}</td>
                            <td class="px-4 py-2 text-right text-gray-600">{{ number_format($table['rows']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
