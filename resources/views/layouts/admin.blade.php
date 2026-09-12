<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'System Admin - Barbar SaaS' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-layout="admin" class="bg-gray-100 text-gray-900 font-sans antialiased min-h-screen flex flex-col">

    <!-- Top Navigation Bar -->
    <header class="fixed top-0 left-0 right-0 z-40 h-16 bg-white border-b border-gray-200 shadow-xs">
        <div class="h-full px-4 sm:px-6 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <!-- Mobile Menu Button (Drawer Toggle) -->
                <button id="mobile-sidebar-toggle" type="button"
                        class="lg:hidden p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-200">
                    <span class="sr-only">Open mobile sidebar</span>
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>

                <!-- Desktop Sidebar Collapse/Expand Toggle -->
                <button id="desktop-sidebar-toggle" type="button"
                        title="Toggle Sidebar"
                        class="hidden lg:inline-flex items-center justify-center p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-200 transition">
                    <span class="sr-only">Toggle desktop sidebar</span>
                    <svg id="collapse-icon" class="w-5 h-5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h16"></path>
                    </svg>
                </button>

                <!-- Logo & Platform Badge -->
                <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-2.5">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-slate-900 text-white font-extrabold text-sm shadow-xs">
                        B
                    </span>
                    <span class="font-bold text-lg text-gray-900 tracking-tight">Barbar <span class="text-blue-600">Admin</span></span>
                    <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-800 border border-red-200">
                        System Admin
                    </span>
                </a>
            </div>

            <!-- Header Right Section -->
            <div class="flex items-center space-x-3">
                <div class="text-right hidden sm:block">
                    <div class="text-xs font-bold text-gray-900">{{ auth()->user()->name }}</div>
                    <div class="text-[11px] text-gray-500">{{ auth()->user()->email }}</div>
                </div>

                <form action="{{ route('admin.logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            title="Log Out"
                            class="inline-flex items-center text-xs font-semibold text-gray-700 hover:text-red-700 bg-gray-100 hover:bg-red-50 hover:border-red-200 border border-gray-200 rounded-lg px-3 py-1.5 transition">
                        <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span class="hidden sm:inline">Log Out</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Mobile Drawer Backdrop -->
    <div id="sidebar-backdrop" class="fixed inset-0 top-16 z-20 bg-gray-900/50 backdrop-blur-xs transition-opacity hidden lg:hidden"></div>

    <!-- Responsive Collapsible Sidebar -->
    <aside id="app-sidebar"
           class="fixed top-16 left-0 z-30 w-64 h-[calc(100vh-4rem)] bg-white border-r border-gray-200 transition-all duration-300 -translate-x-full lg:translate-x-0 flex flex-col justify-between overflow-y-auto"
           aria-label="Admin Navigation Sidebar">

        <!-- Sidebar Top: Navigation Menu -->
        <div class="p-3">
            <div class="lg:hidden flex items-center justify-between pb-3 mb-2 border-b border-gray-100">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Navigation</span>
                <button id="mobile-sidebar-close" type="button" class="p-1.5 text-gray-400 hover:text-gray-700 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <ul class="space-y-1.5 font-medium">
                <!-- Dashboard -->
                <li>
                    <a href="{{ route('admin.dashboard') }}"
                       title="Dashboard"
                       class="sidebar-link flex items-center p-2.5 rounded-xl transition group {{ request()->routeIs('admin.dashboard') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 flex-shrink-0 transition {{ request()->routeIs('admin.dashboard') ? 'text-white' : 'text-gray-500 group-hover:text-gray-900' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <span class="sidebar-text ml-3 text-sm whitespace-nowrap">Dashboard</span>
                    </a>
                </li>

                <!-- Companies -->
                <li>
                    <a href="{{ route('admin.companies.index') }}"
                       title="Companies"
                       class="sidebar-link flex items-center p-2.5 rounded-xl transition group {{ request()->routeIs('admin.companies.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 flex-shrink-0 transition {{ request()->routeIs('admin.companies.*') ? 'text-white' : 'text-gray-500 group-hover:text-gray-900' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        <span class="sidebar-text ml-3 text-sm whitespace-nowrap">Companies</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Sidebar Bottom: Logged-in User & Logout -->
        <div class="p-3 border-t border-gray-200 bg-gray-50/75">
            <div class="sidebar-text mb-3">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="overflow-hidden">
                        <div class="text-xs font-bold text-gray-900 truncate">{{ auth()->user()->name }}</div>
                        <div class="text-[11px] text-gray-500 truncate">System Administrator</div>
                    </div>
                </div>
            </div>

            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit"
                        title="Log Out"
                        class="sidebar-link w-full flex items-center justify-center p-2 rounded-lg text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 transition">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span class="sidebar-text ml-2">Log Out</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Wrapper (Shifts correctly with sidebar) -->
    <div id="main-content" class="lg:ml-64 pt-16 min-h-screen flex flex-col justify-between transition-all duration-300">
        <main class="p-4 sm:p-6 lg:p-8 flex-grow w-full max-w-7xl mx-auto">
            @if (session('status'))
                <div class="p-4 mb-6 text-sm text-green-800 rounded-xl bg-green-50 border border-green-200 shadow-xs" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="p-4 mb-6 text-sm text-red-800 rounded-xl bg-red-50 border border-red-200 shadow-xs" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="bg-white border-t border-gray-200 py-4 px-6 text-center text-xs text-gray-500">
            &copy; {{ date('Y') }} Barbar SaaS Administration Platform. All rights reserved.
        </footer>
    </div>

    <!-- Collapsible Sidebar Controller Script -->
    <script>
        (function() {
            const sidebar = document.getElementById('app-sidebar');
            const mainContent = document.getElementById('main-content');
            const backdrop = document.getElementById('sidebar-backdrop');
            const toggleDesktopBtn = document.getElementById('desktop-sidebar-toggle');
            const toggleMobileBtn = document.getElementById('mobile-sidebar-toggle');
            const closeMobileBtn = document.getElementById('mobile-sidebar-close');
            const sidebarTexts = document.querySelectorAll('.sidebar-text');
            const navLinks = document.querySelectorAll('.sidebar-link');
            const storageKey = 'sidebar-collapsed-admin';

            let isCollapsed = localStorage.getItem(storageKey) === 'true';

            function applyDesktopState() {
                if (window.innerWidth >= 1024) {
                    if (isCollapsed) {
                        sidebar.classList.remove('w-64');
                        sidebar.classList.add('w-20');
                        mainContent.classList.remove('lg:ml-64');
                        mainContent.classList.add('lg:ml-20');
                        sidebarTexts.forEach(el => el.classList.add('lg:hidden'));
                        navLinks.forEach(el => el.classList.add('lg:justify-center'));
                    } else {
                        sidebar.classList.remove('w-20');
                        sidebar.classList.add('w-64');
                        mainContent.classList.remove('lg:ml-20');
                        mainContent.classList.add('lg:ml-64');
                        sidebarTexts.forEach(el => el.classList.remove('lg:hidden'));
                        navLinks.forEach(el => el.classList.remove('lg:justify-center'));
                    }
                }
            }

            function toggleDesktop() {
                isCollapsed = !isCollapsed;
                localStorage.setItem(storageKey, isCollapsed ? 'true' : 'false');
                applyDesktopState();
            }

            function openMobile() {
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                backdrop.classList.remove('hidden');
            }

            function closeMobile() {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
                backdrop.classList.add('hidden');
            }

            if (toggleDesktopBtn) toggleDesktopBtn.addEventListener('click', toggleDesktop);
            if (toggleMobileBtn) toggleMobileBtn.addEventListener('click', openMobile);
            if (closeMobileBtn) closeMobileBtn.addEventListener('click', closeMobile);
            if (backdrop) backdrop.addEventListener('click', closeMobile);

            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    closeMobile();
                    applyDesktopState();
                } else {
                    sidebar.classList.remove('w-20');
                    sidebar.classList.add('w-64');
                    sidebarTexts.forEach(el => el.classList.remove('lg:hidden'));
                    navLinks.forEach(el => el.classList.remove('lg:justify-center'));
                }
            });

            applyDesktopState();
        })();
    </script>
</body>
</html>
