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
    <!-- Main layout wrapper — sidebar + content -->
    <div class="flex min-h-screen">

        <!-- ═══ Sidebar Navigation ═══ -->
        <aside class="sidebar w-64 bg-gray-900 text-gray-300 flex flex-col fixed h-full overflow-y-auto">
            <!-- App branding / logo area -->
            <div class="p-4 border-b border-gray-700">
                <!-- App name with link to dashboard -->
                <a href="{{ route('dashboard') }}" class="text-xl font-bold text-white">
                    HWS
                </a>
                <!-- Subtitle -->
                <p class="text-xs text-gray-500 mt-1">Hourly Bill Tracking</p>
            </div>

            <!-- Navigation links -->
            <nav class="flex-1 p-4 space-y-1">
                <!-- Dashboard link -->
                <a href="{{ route('dashboard') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('dashboard') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <!-- Dashboard icon (SVG) -->
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </a>

                <!-- Section label -->
                <p class="text-xs text-gray-600 uppercase tracking-wider pt-4 pb-1 px-3">Billing</p>

                <!-- Scan / Import link -->
                <a href="{{ route('scan.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('scan.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Run Scan
                </a>

                <!-- Invoices link -->
                <a href="{{ route('invoices.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('invoices.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                    </svg>
                    Invoices
                </a>

                <!-- Section label -->
                <p class="text-xs text-gray-600 uppercase tracking-wider pt-4 pb-1 px-3">Manage</p>

                <!-- Clients link -->
                <a href="{{ route('clients.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('clients.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Clients
                </a>

                <!-- Employees link -->
                <a href="{{ route('employees.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('employees.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                    </svg>
                    Employees
                </a>

                <!-- Section label -->
                <p class="text-xs text-gray-600 uppercase tracking-wider pt-4 pb-1 px-3">System</p>

                <!-- Email Templates link -->
                <a href="{{ route('emails.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('emails.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Email Templates
                </a>

                <!-- Lists link -->
                <a href="{{ route('lists.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('lists.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    Lists
                </a>

                <!-- Settings link -->
                <a href="{{ route('settings.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->routeIs('settings.*') ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Settings
                </a>
            </nav>

            <!-- Logout at the bottom of sidebar -->
            <div class="p-4 border-t border-gray-700">
                <!-- Logout form -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <!-- Logout button styled as a link -->
                    <button type="submit" class="flex items-center w-full px-3 py-2 rounded-lg text-sm text-gray-400 hover:bg-gray-800 hover:text-white">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- ═══ Main Content Area ═══ -->
        <main class="flex-1 ml-64">
            <!-- Top bar with page title -->
            <header class="bg-white shadow-sm border-b border-gray-200 px-8 py-4">
                <!-- Page title from child view -->
                <h1 class="text-2xl font-semibold text-gray-800">@yield('header', 'Dashboard')</h1>
            </header>

            <!-- Flash messages (success/error) -->
            <div class="px-8 pt-4">
                <!-- Success message -->
                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4" x-data="{ show: true }" x-show="show">
                        <!-- Close button -->
                        <div class="flex justify-between items-center">
                            <span>{{ session('success') }}</span>
                            <button @click="show = false" class="text-green-500 hover:text-green-700">&times;</button>
                        </div>
                    </div>
                @endif

                <!-- Error message -->
                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4" x-data="{ show: true }" x-show="show">
                        <!-- Close button -->
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
</body>
</html>
