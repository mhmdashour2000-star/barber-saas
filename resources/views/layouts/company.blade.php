<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Salon Dashboard - Barbar SaaS' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-layout="company" class="bg-gray-100 text-gray-900 font-sans antialiased min-h-screen flex flex-col">

    <!-- Top Navigation Bar -->
    <header class="fixed top-0 left-0 right-0 z-40 h-16 bg-white border-b border-gray-200 shadow-xs">
        <div class="h-full px-4 sm:px-6 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <!-- Mobile Menu Button (Drawer Toggle) -->
                <button id="mobile-sidebar-toggle" type="button" aria-controls="app-sidebar" aria-expanded="false"
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
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h16"></path>
                    </svg>
                </button>

                <!-- Logo & Salon Indicator -->
                <a href="{{ route('company.dashboard') }}" class="flex items-center space-x-2.5">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-600 text-white font-bold shadow-xs">
                        B
                    </span>
                    <span class="font-bold text-lg text-gray-900 tracking-tight">Barbar <span class="text-blue-600">Salon</span></span>
                </a>

                @if(auth()->user()->company)
                    <span class="hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                        {{ auth()->user()->company->name }} ({{ auth()->user()->company->code }})
                    </span>
                @endif
            </div>

            <!-- Header Right Section -->
            <div class="flex items-center space-x-3">
                <div class="text-right hidden sm:block">
                    <div class="text-xs font-bold text-gray-900">{{ auth()->user()->name }}</div>
                    <div class="text-[11px] text-gray-500">Salon Manager</div>
                </div>

                <form action="{{ route('logout') }}" method="POST" class="inline">
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
           aria-label="Salon Manager Sidebar">

        <!-- Sidebar Top: Navigation Menu -->
        <div class="p-3">
            <div class="lg:hidden flex items-center justify-between pb-3 mb-2 border-b border-gray-100">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Navigation</span>
                <button id="mobile-sidebar-close" type="button" aria-label="Close sidebar" class="p-1.5 text-gray-400 hover:text-gray-700 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <ul class="space-y-1.5 font-medium">
                <!-- Dashboard -->
                <li>
                    <a href="{{ route('company.dashboard') }}"
                       title="Dashboard"
                       class="sidebar-link flex items-center p-2.5 rounded-xl transition group {{ request()->routeIs('company.dashboard') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 flex-shrink-0 transition {{ request()->routeIs('company.dashboard') ? 'text-white' : 'text-gray-500 group-hover:text-gray-900' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <span class="sidebar-text ml-3 text-sm whitespace-nowrap">Dashboard</span>
                    </a>
                </li>

                <!-- Appointments -->
                <li>
                    <a href="{{ route('company.appointments.index') }}" title="Appointments"
                       class="sidebar-link flex items-center p-2.5 rounded-xl transition group {{ request()->routeIs('company.appointments.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                        </svg>
                        <span class="sidebar-text ml-3 text-sm whitespace-nowrap">Appointments</span>
                    </a>
                </li>

                <!-- Employees -->
                <li>
                    <a href="{{ route('company.employees.index') }}"
                       title="Employees"
                       class="sidebar-link flex items-center p-2.5 rounded-xl transition group {{ request()->routeIs('company.employees.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 flex-shrink-0 transition {{ request()->routeIs('company.employees.*') ? 'text-white' : 'text-gray-500 group-hover:text-gray-900' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <span class="sidebar-text ml-3 text-sm whitespace-nowrap">Employees</span>
                    </a>
                </li>

                <!-- Services -->
                <li>
                    <a href="{{ route('company.services.index') }}"
                       title="Services"
                       class="sidebar-link flex items-center p-2.5 rounded-xl transition group {{ request()->routeIs('company.services.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 flex-shrink-0 transition {{ request()->routeIs('company.services.*') ? 'text-white' : 'text-gray-500 group-hover:text-gray-900' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879a3 3 0 11-4.242-4.242L10.758 4.758a3 3 0 014.242 4.242L12 12z"></path>
                        </svg>
                        <span class="sidebar-text ml-3 text-sm whitespace-nowrap">Services</span>
                    </a>
                </li>

                <!-- Availability -->
                <li>
                    <a href="{{ route('company.availability.index') }}"
                       title="Availability"
                       class="sidebar-link flex items-center p-2.5 rounded-xl transition group {{ request()->routeIs('company.availability.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 flex-shrink-0 transition {{ request()->routeIs('company.availability.*') ? 'text-white' : 'text-gray-500 group-hover:text-gray-900' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="sidebar-text ml-3 text-sm whitespace-nowrap">Availability</span>
                    </a>
                </li>

                <!-- Customers -->
                <li>
                    <a href="{{ route('company.customers.index') }}"
                       title="Customers"
                       class="sidebar-link flex items-center p-2.5 rounded-xl transition group {{ request()->routeIs('company.customers.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 flex-shrink-0 transition {{ request()->routeIs('company.customers.*') ? 'text-white' : 'text-gray-500 group-hover:text-gray-900' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <span class="sidebar-text ml-3 text-sm whitespace-nowrap">Customers</span>
                    </a>
                </li>

                <!-- Settings -->
                <li>
                    <a href="{{ route('company.settings.edit') }}"
                       title="Settings"
                       class="sidebar-link flex items-center p-2.5 rounded-xl transition group {{ request()->routeIs('company.settings.*') ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 flex-shrink-0 transition {{ request()->routeIs('company.settings.*') ? 'text-white' : 'text-gray-500 group-hover:text-gray-900' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="sidebar-text ml-3 text-sm whitespace-nowrap">Settings</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Sidebar Bottom: Salon Info, Manager Info & Logout -->
        <div class="p-3 border-t border-gray-200 bg-gray-50/75">
            <div class="sidebar-text mb-3">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-bold text-gray-900 truncate">{{ auth()->user()->company?->name ?? 'Salon' }}</span>
                    @if(auth()->user()->company?->code)
                        <span class="font-mono text-[10px] font-bold bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded">
                            {{ auth()->user()->company->code }}
                        </span>
                    @endif
                </div>
                <div class="text-[11px] text-gray-500 truncate">
                    Manager: <span class="font-medium text-gray-700">{{ auth()->user()->name }}</span>
                </div>
            </div>

            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit"
                        title="Log Out"
                        class="sidebar-link w-full flex items-center justify-center p-2 rounded-lg text-xs font-semibold text-gray-700 hover:text-red-700 bg-white hover:bg-red-50 border border-gray-200 transition">
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
            &copy; {{ date('Y') }} Barbar SaaS. Barber Salon Appointment Platform.
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
            const storageKey = 'sidebar-collapsed-company';

            let isCollapsed = localStorage.getItem(storageKey) === 'true';
            // Translated-offscreen navigation must not remain in the keyboard tab order.
            sidebar.inert = window.innerWidth < 1024;

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
                sidebar.inert = false;
                toggleMobileBtn.setAttribute('aria-expanded', 'true');
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                backdrop.classList.remove('hidden');
                closeMobileBtn?.focus();
            }

            function closeMobile() {
                const restoreFocus = window.innerWidth < 1024 && sidebar.contains(document.activeElement);
                sidebar.inert = window.innerWidth < 1024;
                toggleMobileBtn.setAttribute('aria-expanded', 'false');
                if (restoreFocus) toggleMobileBtn.focus();
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
                backdrop.classList.add('hidden');
            }

            if (toggleDesktopBtn) toggleDesktopBtn.addEventListener('click', toggleDesktop);
            if (toggleMobileBtn) toggleMobileBtn.addEventListener('click', openMobile);
            if (closeMobileBtn) closeMobileBtn.addEventListener('click', closeMobile);
            if (backdrop) backdrop.addEventListener('click', closeMobile);
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && window.innerWidth < 1024) closeMobile();
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    closeMobile();
                    applyDesktopState();
                } else {
                    sidebar.inert = sidebar.classList.contains('-translate-x-full');
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
