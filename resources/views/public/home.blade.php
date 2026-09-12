<x-layouts.guest>
    <x-slot:title>Barbar SaaS - Barber Salon Appointment Management</x-slot:title>

    <!-- Hero Section -->
    <section class="relative bg-white overflow-hidden py-16 lg:py-24 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200 mb-6">
                    <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                    Barber Salon Appointment SaaS Platform
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 tracking-tight leading-tight">
                    Smart Appointment Management for <span class="text-blue-600">Modern Barber Salons</span>
                </h1>

                <p class="mt-6 text-lg sm:text-xl text-gray-600 leading-relaxed">
                    Streamline your salon calendar, organize staff schedules, and prepare your business for effortless future WhatsApp client bookings.
                </p>

                <div class="mt-8 flex flex-col sm:flex-row justify-center gap-4">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-3.5 text-base font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-md transition">
                        Register Your Salon
                        <svg class="w-5 h-5 ml-2 -mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </a>
                    <a href="#features" class="inline-flex items-center justify-center px-6 py-3.5 text-base font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                        Explore Features
                    </a>
                </div>

                <div class="mt-6 text-xs text-gray-500">
                    ✓ First month free &nbsp;•&nbsp; ✓ Cancel anytime &nbsp;•&nbsp; ✓ No setup fees
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-xs font-semibold text-blue-600 tracking-wide uppercase">Core Capabilities</h2>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 tracking-tight sm:text-4xl">
                    Everything you need to organize your salon
                </p>
                <p class="mt-3 text-base text-gray-600">
                    A purpose-built SaaS solution tailored to the operational flow of professional barber salons.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="bg-white p-8 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Appointment Management</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        Manage salon appointments from one organized system. Track incoming bookings, prevent double scheduling, and keep your daily calendar clear.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white p-8 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Flexible Service Scheduling</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        Services can have their own weekly availability and durations. Set precise working slots for hair styling, beard grooming, and special treatments.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white p-8 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">WhatsApp Booking Ready</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">
                        The system architecture is being prepared for future customer booking through WhatsApp, allowing clients to reserve slots in natural conversation.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-20 bg-white border-t border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-xs font-semibold text-blue-600 tracking-wide uppercase">Simple Transparent Pricing</h2>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 tracking-tight sm:text-4xl">
                    One simple plan for your salon
                </p>
                <p class="mt-3 text-base text-gray-600">
                    Everything you need to power your barber salon operations without complicated tiers.
                </p>
            </div>

            <!-- Single Plan Card -->
            <div class="max-w-lg mx-auto bg-white rounded-2xl border-2 border-blue-600 shadow-xl overflow-hidden">
                <div class="p-8 sm:p-10">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900">Salon Pro</h3>
                            <p class="text-sm text-gray-500 mt-1">Full access to the appointment platform</p>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                            First Month Free
                        </span>
                    </div>

                    <div class="mt-6 flex items-baseline">
                        <span class="text-5xl font-extrabold text-gray-900 tracking-tight">399</span>
                        <span class="text-xl font-semibold text-gray-600 ml-1">TRY</span>
                        <span class="text-sm font-medium text-gray-500 ml-2">/ month</span>
                    </div>

                    <p class="mt-2 text-xs text-gray-500">
                        Try free for 30 days. No upfront payment required during initial registration.
                    </p>

                    <!-- Features Checklist -->
                    <ul class="mt-8 space-y-3.5 text-sm text-gray-700">
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Complete Appointment Management</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Service Scheduling & Duration Settings</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Dedicated Salon Profile & Public Code</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Future WhatsApp Booking Integration Ready</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Standard Online Support</span>
                        </li>
                    </ul>

                    <div class="mt-8">
                        <a href="{{ route('register') }}" class="w-full inline-flex items-center justify-center px-6 py-3.5 text-base font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow transition">
                            Start Your Free Month
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-16 bg-blue-600 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
                Ready to organize your barber salon appointments?
            </h2>
            <p class="mt-4 text-lg text-blue-100 max-w-2xl mx-auto">
                Join our platform today with a 30-day free trial. Setup takes less than 2 minutes.
            </p>
            <div class="mt-8">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-8 py-3.5 text-base font-semibold text-blue-600 bg-white hover:bg-gray-100 rounded-lg shadow-md transition">
                    Register Salon Account
                </a>
            </div>
        </div>
    </section>
</x-layouts.guest>
