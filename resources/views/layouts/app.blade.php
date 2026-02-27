<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Character encoding -->
    <meta charset="UTF-8">
    <!-- Responsive viewport -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- CSRF token for forms -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Page title — uses section or falls back to config app name -->
    <title>@yield('title', config('hws.app_name'))</title>
    <!-- Tailwind CSS via CDN — lightweight, no build step needed -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js for interactive UI components -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Custom scrollbar styling for the sidebar */
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 2px; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    @auth
    {{-- ═══ AUTHENTICATED LAYOUT — sidebar + content ═══ --}}
    <div class="flex min-h-screen">

        <!-- ═══ Sidebar Navigation ═══ -->
        <aside class="sidebar w-64 bg-gray-900 text-gray-300 flex flex-col fixed h-full overflow-y-auto">
            <!-- App branding / logo area -->
            <div class="p-4 border-b border-gray-700">
                <!-- App name with link to dashboard -->
                <a href="{{ route('dashboard') }}" class="text-xl font-bold text-white">
                    Hexa Billing
                </a>
                <!-- Subtitle with version -->
                <p class="text-xs text-gray-500 mt-1">v{{ config('hws.version') }}</p>
            </div>

            <!-- Navigation links -->
            <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
                <!-- Dashboard link -->
                <a href="{{ route('dashboard') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('dashboard') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </a>

                {{-- ═══ SECTION 1: Hourly Billing ═══ --}}
                <p class="text-xs text-gray-600 uppercase tracking-wider pt-4 pb-1 px-3">Hourly Billing</p>

                <a href="{{ route('scan.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('scan.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Run Scan
                </a>

                <a href="{{ route('invoices.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('invoices.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                    </svg>
                    Invoices
                </a>

                <a href="{{ route('employees.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('employees.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                    </svg>
                    Employees
                </a>

                {{-- ═══ SECTION 2: Cloud Services ═══ --}}
                <p class="text-xs text-gray-600 uppercase tracking-wider pt-4 pb-1 px-3">Cloud Services</p>

                <a href="{{ route('hosting.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('hosting.index') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                    </svg>
                    Overview
                </a>

                <a href="{{ route('hosting.servers') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('hosting.server*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                    </svg>
                    WHM Servers
                </a>

                <a href="{{ route('hosting.accounts') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('hosting.account*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
                    </svg>
                    Accounts
                </a>

                {{-- ═══ SECTION 3: Invoice Generator ═══ --}}
                <p class="text-xs text-gray-600 uppercase tracking-wider pt-4 pb-1 px-3">Tools</p>

                <a href="{{ route('invoice-generator.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('invoice-generator.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Invoice Generator
                </a>

                {{-- ═══ SHARED: Clients + System ═══ --}}
                <p class="text-xs text-gray-600 uppercase tracking-wider pt-4 pb-1 px-3">Manage</p>

                <a href="{{ route('clients.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('clients.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Clients
                </a>

                <a href="{{ route('emails.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('emails.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Email Templates
                </a>

                <a href="{{ route('lists.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('lists.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    Lists
                </a>

                <p class="text-xs text-gray-600 uppercase tracking-wider pt-4 pb-1 px-3">System</p>

                <a href="{{ route('settings.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('settings.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Settings
                </a>

                <a href="{{ route('info.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('info.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    System Info
                </a>

                {{-- Debug Modules — only visible when debug mode is ON --}}
                @if(config('hws.debug_mode'))
                    <a href="{{ route('debug.index') }}"
                       class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('debug.*') ? 'bg-yellow-600 text-white' : 'text-yellow-400 hover:bg-gray-800 hover:text-yellow-300' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Debug Modules
                    </a>
                @endif
            </nav>

            <!-- Logged-in user + Logout at the bottom of sidebar -->
            <div class="p-4 border-t border-gray-700">
                {{-- Current user display --}}
                <p class="text-xs text-gray-500 mb-2 px-3">{{ Auth::user()->email ?? '' }}</p>
                <!-- Logout form -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center w-full px-3 py-2 rounded-lg text-sm text-gray-400 hover:bg-gray-800 hover:text-white">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- ═══ Main Content Area (with sidebar offset) ═══ -->
        <main class="flex-1 ml-64">
            <!-- Top bar with page title -->
            <header class="bg-white shadow-sm border-b border-gray-200 px-8 py-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-semibold text-gray-800">@yield('header', 'Dashboard')</h1>
                    {{-- Debug mode badge --}}
                    @if(config('hws.debug_mode'))
                        <span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2.5 py-0.5 rounded">DEBUG MODE</span>
                    @endif
                </div>
            </header>

            <!-- Flash messages (success/error) -->
            <div class="px-8 pt-4">
                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4" x-data="{ show: true }" x-show="show">
                        <div class="flex justify-between items-center">
                            <span>{{ session('success') }}</span>
                            <button @click="show = false" class="text-green-500 hover:text-green-700">&times;</button>
                        </div>
                    </div>
                @endif
                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4" x-data="{ show: true }" x-show="show">
                        <div class="flex justify-between items-center">
                            <span>{{ session('error') }}</span>
                            <button @click="show = false" class="text-red-500 hover:text-red-700">&times;</button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Page content from child view -->
            <div class="p-8">
                @yield('content')
            </div>
        </main>
    </div>

    @else
    {{-- ═══ GUEST LAYOUT — no sidebar, centered content ═══ --}}
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="w-full max-w-md">
            {{-- Logo / Branding --}}
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Hexa Billing</h1>
                <p class="text-sm text-gray-500 mt-1">v{{ config('hws.version') }}</p>
            </div>

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                    {{ session('error') }}
                </div>
            @endif
            @if(session('status'))
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg mb-4">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Page content --}}
            @yield('content')
        </div>
    </div>
    @endauth

</body>
</html>
