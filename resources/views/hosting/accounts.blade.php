{{-- Hosting Accounts list — all accounts across all servers --}}
@extends('layouts.app')
@section('title', 'Hosting Accounts')
@section('header', 'Hosting Accounts')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    {{-- Filters --}}
    <form method="GET" class="flex gap-4 mb-4">
        <select name="server" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">All Servers</option>
            @foreach($servers as $s)
                <option value="{{ $s->id }}" {{ request('server') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
            @endforeach
        </select>
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">All Statuses</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
            <option value="terminated" {{ request('status') === 'terminated' ? 'selected' : '' }}>Terminated</option>
        </select>
        <button type="submit" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Filter</button>
    </form>

    @if($accounts->isEmpty())
        <p class="text-sm text-gray-400 italic">No hosting accounts found. Sync a WHM server to discover accounts.</p>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-gray-600">Domain</th>
                    <th class="px-4 py-2 text-left text-gray-600">Username</th>
                    <th class="px-4 py-2 text-left text-gray-600">Server</th>
                    <th class="px-4 py-2 text-left text-gray-600">Owner</th>
                    <th class="px-4 py-2 text-center text-gray-600">Subscriptions</th>
                    <th class="px-4 py-2 text-center text-gray-600">Status</th>
                    <th class="px-4 py-2 text-right text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($accounts as $account)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $account->domain }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $account->username }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $account->whmServer->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $account->client->name ?? '<span class="text-gray-400 italic">Unassigned</span>' }}</td>
                        <td class="px-4 py-3 text-center">{{ $account->activeSubscriptions->count() }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs
                                {{ $account->status === 'active' ? 'bg-green-100 text-green-700' : '' }}
                                {{ $account->status === 'suspended' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                {{ $account->status === 'terminated' ? 'bg-red-100 text-red-700' : '' }}">
                                {{ ucfirst($account->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('hosting.account.edit', $account) }}" class="text-blue-600 hover:underline text-sm">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4">{{ $accounts->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
