<x-layouts.company>
    <x-slot:title>Salon Dashboard - Barbar SaaS</x-slot:title>

    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Salon Management Overview</h1>
            <p class="text-sm text-gray-500 mt-1">Welcome back, {{ $user->name }}. Manage your salon settings and booking policy rules.</p>
        </div>
        <div>
            <a href="{{ route('company.settings.edit') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Manage Settings
            </a>
        </div>
    </div>

    <!-- Status Banners -->
    @if($company?->status === 'suspended')
        <div class="p-5 mb-8 text-sm text-red-900 rounded-xl bg-red-50 border-2 border-red-200 flex items-center space-x-3">
            <svg class="w-6 h-6 flex-shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
                <span class="font-bold text-base">Your company account is currently suspended.</span>
                <span class="block text-xs mt-1 text-red-700">Please contact platform administration. Staff management and operational modifications are locked.</span>
            </div>
        </div>
    @elseif($company?->status === 'pending')
        <div class="p-4 mb-8 text-sm text-amber-900 rounded-xl bg-amber-50 border border-amber-200 flex items-center space-x-3">
            <svg class="w-5 h-5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <span class="font-bold">Your salon registration is pending platform review.</span>
                <span class="block text-xs mt-0.5 text-amber-700">Platform administrators are reviewing your submission.</span>
            </div>
        </div>
    @endif

    <!-- Company Identity Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <!-- Salon Name -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Salon Name</span>
            <h2 class="text-xl font-bold text-gray-900 mt-1 truncate">{{ $company?->name ?? 'Not Assigned' }}</h2>
            <p class="text-xs text-gray-500 mt-2 truncate">{{ $company?->address ?: 'No address specified' }}</p>
        </div>

        <!-- Public Code -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Public Company Code</span>
            <h2 class="text-xl font-mono font-bold text-blue-600 mt-1">{{ $company?->code ?? 'N/A' }}</h2>
            <p class="text-xs text-gray-500 mt-2">Client Booking Identifier</p>
        </div>

        <!-- Manager & Phone -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Manager & Phone</span>
            <h2 class="text-xl font-bold text-gray-900 mt-1 truncate">{{ $user->name }}</h2>
            <p class="text-xs text-gray-500 mt-2">{{ $company?->phone ?? $user->email }}</p>
        </div>

        <!-- Company Status -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Account Status</span>
            <div class="mt-2">
                @if($company?->status === 'active')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                        <span class="w-2 h-2 rounded-full bg-green-500 mr-1.5"></span>
                        Active
                    </span>
                @elseif($company?->status === 'pending')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                        <span class="w-2 h-2 rounded-full bg-amber-500 mr-1.5"></span>
                        Pending Review
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                        <span class="w-2 h-2 rounded-full bg-red-500 mr-1.5"></span>
                        {{ ucfirst($company?->status ?? 'Suspended') }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-2">Access & Operational Status</p>
        </div>
    </div>

    <!-- Employee Overview & Statistics Section -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900">Salon Staff &amp; Employees</h2>
            <div class="flex items-center space-x-3">
                @if($company?->status !== 'suspended')
                    <a href="{{ route('company.employees.create') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                        + Add New Employee
                    </a>
                    <span class="text-gray-300">|</span>
                @endif
                <a href="{{ route('company.employees.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                    View All &rarr;
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <!-- Total Employees -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Staff</span>
                    <span class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline">
                    <span class="text-2xl font-bold text-gray-900">{{ $employeeStats['total'] ?? 0 }}</span>
                    <span class="ml-2 text-xs text-gray-500 font-medium">registered</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">All employees assigned to this salon</p>
            </div>

            <!-- Active Employees -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Active Staff</span>
                    <span class="p-2 bg-green-50 text-green-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline">
                    <span class="text-2xl font-bold text-green-700">{{ $employeeStats['active'] ?? 0 }}</span>
                    <span class="ml-2 text-xs text-green-600 font-medium">active</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Available for shifts and bookings</p>
            </div>

            <!-- Inactive Employees -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Inactive Staff</span>
                    <span class="p-2 bg-gray-100 text-gray-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline">
                    <span class="text-2xl font-bold text-gray-600">{{ $employeeStats['inactive'] ?? 0 }}</span>
                    <span class="ml-2 text-xs text-gray-500 font-medium">deactivated</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Suspended or temporarily off duty</p>
            </div>
        </div>
    </div>

    <!-- Salon Services Overview & Statistics Section -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900">Salon Services &amp; Catalog</h2>
            <div class="flex items-center space-x-3">
                @if($company?->status !== 'suspended')
                    <a href="{{ route('company.services.create') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                        + Add New Service
                    </a>
                    <span class="text-gray-300">|</span>
                @endif
                <a href="{{ route('company.services.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                    View All &rarr;
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <!-- Total Services -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Services</span>
                    <span class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879a3 3 0 11-4.242-4.242L10.758 4.758a3 3 0 014.242 4.242L12 12z"></path>
                        </svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline">
                    <span class="text-2xl font-bold text-gray-900">{{ $serviceStats['total'] ?? 0 }}</span>
                    <span class="ml-2 text-xs text-gray-500 font-medium">in catalog</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">All offerings configured for this salon</p>
            </div>

            <!-- Active Services -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Active Services</span>
                    <span class="p-2 bg-green-50 text-green-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline">
                    <span class="text-2xl font-bold text-green-700">{{ $serviceStats['active'] ?? 0 }}</span>
                    <span class="ml-2 text-xs text-green-600 font-medium">active</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Available for client booking schedules</p>
            </div>

            <!-- Inactive Services -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Inactive Services</span>
                    <span class="p-2 bg-gray-100 text-gray-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline">
                    <span class="text-2xl font-bold text-gray-600">{{ $serviceStats['inactive'] ?? 0 }}</span>
                    <span class="ml-2 text-xs text-gray-500 font-medium">deactivated</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Temporarily hidden from booking availability</p>
            </div>
        </div>
    </div>

    <!-- Salon Customers Overview Section -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900">Salon Customers &amp; Client Roster</h2>
            <a href="{{ route('company.customers.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                View Directory &rarr;
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <!-- Total Customers -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Registered Clients</span>
                    <span class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline">
                    <span class="text-2xl font-bold text-gray-900">{{ $customerStats['total'] ?? 0 }}</span>
                    <span class="ml-2 text-xs text-gray-500 font-medium">clients</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">All unique phone clients registered with this salon</p>
            </div>

            <!-- Blocked Customers -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Currently Restricted</span>
                    <span class="p-2 bg-red-50 text-red-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline">
                    <span class="text-2xl font-bold {{ ($customerStats['blocked'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $customerStats['blocked'] ?? 0 }}</span>
                    <span class="ml-2 text-xs text-gray-500 font-medium">blocked</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Clients currently barred from booking new appointments</p>
            </div>
        </div>
    </div>

    <!-- Booking Policy & Rule Cards Section -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900">Current Booking Rules & Policy</h2>
            <a href="{{ route('company.settings.edit') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                Edit Rules &rarr;
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Booking Days Ahead -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Booking Window</span>
                    <span class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline">
                    <span class="text-2xl font-bold text-gray-900">{{ $company?->booking_days_ahead ?? 7 }}</span>
                    <span class="ml-2 text-xs text-gray-500 font-medium">days ahead</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Maximum advance booking allowed</p>
            </div>

            <!-- Late Cancellation Threshold -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Cancellation Limit</span>
                    <span class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline">
                    <span class="text-2xl font-bold text-gray-900">{{ $company?->late_cancellation_hours ?? 24 }}</span>
                    <span class="ml-2 text-xs text-gray-500 font-medium">hours prior</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Notice required before appointment</p>
            </div>

            <!-- Violation Limit -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Violation Limit</span>
                    <span class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline">
                    <span class="text-2xl font-bold text-gray-900">{{ $company?->violation_limit ?? 3 }}</span>
                    <span class="ml-2 text-xs text-gray-500 font-medium">strikes max</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Violations before customer is blocked</p>
            </div>

            <!-- Block Duration -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Block Duration</span>
                    <span class="p-2 bg-red-50 text-red-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                    </span>
                </div>
                <div class="mt-2 flex items-baseline">
                    <span class="text-2xl font-bold text-gray-900">{{ $company?->block_duration_days ?? 7 }}</span>
                    <span class="ml-2 text-xs text-gray-500 font-medium">days</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Cool-off period for blocked clients</p>
            </div>
        </div>
    </div>

    <!-- Salon Details & Map URL Card -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
        <div class="flex items-start justify-between flex-col md:flex-row gap-4">
            <div class="flex items-start space-x-4">
                <div class="p-3 bg-blue-50 text-blue-600 rounded-xl flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Salon Profile & Location Details</h3>
                    <p class="text-sm text-gray-600 mt-1 leading-relaxed">
                        Keep your salon address and Google Maps location accurate for your clients.
                    </p>
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-gray-600">
                        <div>
                            <span class="font-semibold text-gray-700 block">Address:</span>
                            <span class="text-gray-900 mt-0.5 block">{{ $company?->address ?: 'Not provided yet' }}</span>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-700 block">Google Maps:</span>
                            @if($company?->map_url)
                                <a href="{{ $company->map_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline inline-flex items-center mt-0.5 truncate max-w-xs">
                                    <span>View on Google Maps</span>
                                    <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                </a>
                            @else
                                <span class="text-gray-500 mt-0.5 block">Not provided yet</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-shrink-0 self-end md:self-center">
                <a href="{{ route('company.settings.edit') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 border border-blue-200 bg-blue-50 px-3 py-2 rounded-lg transition">
                    Update Location & Profile
                </a>
            </div>
        </div>
    </div>
</x-layouts.company>
