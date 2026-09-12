<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Barbar SaaS - Barber Salon Appointment Management' }}</title>
    <meta name="description" content="Modern barber salon appointment management system with future WhatsApp booking readiness.">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased min-h-screen flex flex-col justify-between">

    <!-- Header / Navbar -->
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-gray-200">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center space-x-3">
                    <a href="{{ route('home') }}" class="flex items-center space-x-2 text-xl font-bold text-gray-900 tracking-tight">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-blue-600 text-white font-extrabold shadow-sm">
                            B
                        </span>
                        <span>Barbar<span class="text-blue-600">SaaS</span></span>
                    </a>
                </div>

                <div class="hidden md:flex items-center space-x-8 text-sm font-medium text-gray-600">
                    <a href="{{ route('home') }}#features" class="hover:text-blue-600 transition">Features</a>
                    <a href="{{ route('home') }}#pricing" class="hover:text-blue-600 transition">Pricing</a>
                </div>

                <div class="flex items-center space-x-3">
                    @auth
                        @if(auth()->user()->isSystemAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700 px-3 py-2">
                                Admin Dashboard
                            </a>
                        @else
                            <a href="{{ route('company.dashboard') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700 px-3 py-2">
                                Salon Dashboard
                            </a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-blue-600 px-3 py-2 transition">
                            Log In
                        </a>
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition">
                            Get Started
                        </a>
                    @endauth
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">
        @if (session('status'))
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
                <div class="p-4 text-sm text-blue-800 rounded-lg bg-blue-50 border border-blue-200" role="alert">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-16">
        <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
            <div class="md:flex md:items-center md:justify-between">
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-blue-600 text-white font-bold text-xs">
                        B
                    </span>
                    <span class="text-base font-bold text-gray-900">Barbar<span class="text-blue-600">SaaS</span></span>
                    <span class="text-xs text-gray-400 ml-2">Barber Salon Appointment Platform</span>
                </div>
                <div class="mt-4 md:mt-0 flex space-x-6 text-sm text-gray-500">
                    <a href="{{ route('login') }}" class="hover:text-gray-900">Salon Login</a>
                    <a href="{{ route('register') }}" class="hover:text-gray-900">Salon Register</a>
                    <a href="{{ route('admin.login') }}" class="hover:text-gray-900 text-gray-400">System Admin</a>
                </div>
            </div>
            <div class="mt-6 border-t border-gray-100 pt-6 text-xs text-gray-500 text-center">
                &copy; {{ date('Y') }} Barbar SaaS. All rights reserved.
            </div>
        </div>
    </footer>

</body>
</html>
