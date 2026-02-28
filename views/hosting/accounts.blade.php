{{-- Hosting accounts list with search, filters, sync --}}
@extends('layouts.app')
@section('title', 'Hosting Accounts')
@section('header', 'Hosting Accounts')

@section('content')
<div class="space-y-4">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('hosting.accounts') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <input type="text" name="search" value="{{ request('search') }}"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48" placeholder="Search domain/user...">
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
                <option value="removed" {{ request('status') === 'removed' ? 'selected' : '' }}>Removed</option>
            </select>
            <button type="submit" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-300">Filter</button>
        </form>
        <form method="POST" action="{{ route('hosting.sync-all') }}">@csrf
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">Sync All Servers</button>
        </form>
    </div>

    {{-- Accounts table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2.5 text-left text-gray-600">Domain</th>
                    <th class="px-4 py-2.5 text-left text-gray-600">User</th>
                    <th class="px-4 py-2.5 text-left text-gray-600">Owner</th>
                    <th class="px-4 py-2.5 text-left text-gray-600">Server</th>
                    <th class="px-4 py-2.5 text-left text-gray-600">Status</th>
                    <th class="px-4 py-2.5 text-left text-gray-600">Disk</th>
                    <th class="px-4 py-2.5 text-left text-gray-600">Subs</th>
                    <th class="px-4 py-2.5 text-left text-gray-600">Client</th>
                    <th class="px-4 py-2.5 text-left text-gray-600"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $account)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-2.5 font-medium">{{ $account->domain }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-500">{{ $account->username }}</td>
                        <td class="px-4 py-2.5 text-gray-500">{{ $account->owner ?? 'root' }}</td>
                        <td class="px-4 py-2.5 text-gray-500">{{ $account->whmServer->name ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            <span class="text-xs px-2 py-0.5 rounded-full
                                {{ $account->status === 'active' ? 'bg-green-100 text-green-700' : '' }}
                                {{ $account->status === 'suspended' ? 'bg-red-100 text-red-700' : '' }}
                                {{ $account->status === 'removed' ? 'bg-gray-100 text-gray-500' : '' }}">
                                {{ ucfirst($account->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-500">
                            {{ $account->disk_used_mb }}M / {{ $account->disk_limit_mb ?: '∞' }}M
                        </td>
                        <td class="px-4 py-2.5">
                            @if($account->activeSubscriptions->count() > 0)
                                <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">{{ $account->activeSubscriptions->count() }}</span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-gray-500">{{ $account->client->name ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            <a href="{{ route('hosting.account.edit', $account) }}" class="text-xs text-blue-600 hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No accounts found. Sync a WHM server to populate.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $accounts->withQueryString()->links() }}
</div>
@endsection
