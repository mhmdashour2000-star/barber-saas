<x-layouts.company>
    <x-slot:title>Salon Settings - Barbar SaaS</x-slot:title>

    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Salon & Policy Settings</h1>
            <p class="text-sm text-gray-500 mt-1">Configure your salon profile and manage client booking restrictions.</p>
        </div>
        <div>
            <a href="{{ route('company.dashboard') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-200 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Read-Only Salon Identity Banner -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-lg">
                    {{ strtoupper(substr($company->name ?? 'S', 0, 1)) }}
                </div>
                <div>
                    <h2 class="text-base font-bold text-gray-900">{{ $company->name }}</h2>
                    <div class="flex items-center space-x-2 mt-0.5 text-xs text-gray-500">
                        <span>Public Code: <strong class="font-mono text-blue-600">{{ $company->code }}</strong></span>
                        <span>&bull;</span>
                        <span>Manager: <strong>{{ $user->name }}</strong></span>
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <span class="text-xs text-gray-500">Account Status:</span>
                @if($company->status === 'active')
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span>
                        Active
                    </span>
                @elseif($company->status === 'pending')
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5"></span>
                        Pending Review
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span>
                        {{ ucfirst($company->status) }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Suspended Company Alert -->
    @if($company->status === 'suspended')
        <div class="p-4 mb-6 text-sm text-red-800 rounded-xl bg-red-50 border border-red-200 flex items-center space-x-3">
            <svg class="w-5 h-5 flex-shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
                <span class="font-bold">Your company account is currently suspended.</span>
                <span class="block text-xs mt-0.5 text-red-700">Settings modifications are currently locked. Please contact platform administration.</span>
            </div>
        </div>
    @endif

    <!-- Main Settings Form -->
    <form action="{{ route('company.settings.update') }}" method="POST" class="space-y-8">
        @csrf
        @method('PUT')

        <!-- Section 1: Salon Information -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
            <div class="border-b border-gray-200 pb-4 mb-6">
                <h2 class="text-lg font-bold text-gray-900">Salon Information</h2>
                <p class="text-xs text-gray-500 mt-1">General business contact details and location displayed to customers.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Salon Name -->
                <div>
                    <label for="name" class="block mb-2 text-sm font-medium text-gray-900">
                        Salon Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           name="name"
                           value="{{ old('name', $company->name) }}"
                           required
                           class="bg-gray-50 border {{ $errors->has('name') ? 'border-red-500 text-red-900 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500' }} text-sm rounded-lg block w-full p-2.5 transition"
                           placeholder="e.g. Royal Cuts Studio">
                    @error('name')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Salon Phone -->
                <div>
                    <label for="phone" class="block mb-2 text-sm font-medium text-gray-900">
                        Contact Phone <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="phone"
                           name="phone"
                           value="{{ old('phone', $company->phone) }}"
                           required
                           class="bg-gray-50 border {{ $errors->has('phone') ? 'border-red-500 text-red-900 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500' }} text-sm rounded-lg block w-full p-2.5 transition"
                           placeholder="e.g. +90 555 123 4567">
                    @error('phone')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Public Company Code (Read-Only) -->
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-500">
                        Public Salon Code <span class="text-xs text-gray-400 font-normal">(System Generated &bull; Read-Only)</span>
                    </label>
                    <input type="text"
                           value="{{ $company->code }}"
                           disabled
                           readonly
                           class="bg-gray-100 border border-gray-200 text-gray-600 font-mono text-sm rounded-lg block w-full p-2.5 cursor-not-allowed">
                    <p class="mt-1.5 text-xs text-gray-400">Used by customers to find and book at your salon.</p>
                </div>

                <!-- Google Maps URL -->
                <div>
                    <label for="map_url" class="block mb-2 text-sm font-medium text-gray-900">
                        Google Maps Location URL <span class="text-xs text-gray-400 font-normal">(Optional)</span>
                    </label>
                    <input type="url"
                           id="map_url"
                           name="map_url"
                           value="{{ old('map_url', $company->map_url) }}"
                           class="bg-gray-50 border {{ $errors->has('map_url') ? 'border-red-500 text-red-900 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500' }} text-sm rounded-lg block w-full p-2.5 transition"
                           placeholder="https://maps.google.com/...">
                    @error('map_url')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Physical Address -->
                <div class="md:col-span-2">
                    <label for="address" class="block mb-2 text-sm font-medium text-gray-900">
                        Physical Address <span class="text-xs text-gray-400 font-normal">(Optional)</span>
                    </label>
                    <textarea id="address"
                              name="address"
                              rows="3"
                              class="bg-gray-50 border {{ $errors->has('address') ? 'border-red-500 text-red-900 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500' }} text-sm rounded-lg block w-full p-2.5 transition"
                              placeholder="e.g. Istiklal Caddesi No: 42, Beyoglu, Istanbul">{{ old('address', $company->address) }}</textarea>
                    @error('address')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Section 2: Booking Rules & Policy -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
            <div class="border-b border-gray-200 pb-4 mb-6">
                <h2 class="text-lg font-bold text-gray-900">Booking Policy & Rules</h2>
                <p class="text-xs text-gray-500 mt-1">Define appointment scheduling limits and cancellation/violation thresholds.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Booking Days Ahead -->
                <div>
                    <label for="booking_days_ahead" class="block mb-2 text-sm font-medium text-gray-900">
                        Booking Days Ahead <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="number"
                               id="booking_days_ahead"
                               name="booking_days_ahead"
                               min="1"
                               max="90"
                               value="{{ old('booking_days_ahead', $company->booking_days_ahead ?? 7) }}"
                               required
                               class="bg-gray-50 border {{ $errors->has('booking_days_ahead') ? 'border-red-500 text-red-900 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500' }} text-sm rounded-lg block w-full p-2.5 pr-14 transition">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-xs text-gray-400">
                            Days
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500">Allowed range: 1 to 90 days in advance.</p>
                    @error('booking_days_ahead')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Late Cancellation Hours -->
                <div>
                    <label for="late_cancellation_hours" class="block mb-2 text-sm font-medium text-gray-900">
                        Late Cancellation Notice <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="number"
                               id="late_cancellation_hours"
                               name="late_cancellation_hours"
                               min="0"
                               max="168"
                               value="{{ old('late_cancellation_hours', $company->late_cancellation_hours ?? 24) }}"
                               required
                               class="bg-gray-50 border {{ $errors->has('late_cancellation_hours') ? 'border-red-500 text-red-900 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500' }} text-sm rounded-lg block w-full p-2.5 pr-14 transition">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-xs text-gray-400">
                            Hours
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500">Cancellations under this threshold count as violations (0-168 hrs).</p>
                    @error('late_cancellation_hours')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Violation Limit -->
                <div>
                    <label for="violation_limit" class="block mb-2 text-sm font-medium text-gray-900">
                        Violation Limit <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="number"
                               id="violation_limit"
                               name="violation_limit"
                               min="1"
                               max="100"
                               value="{{ old('violation_limit', $company->violation_limit ?? 3) }}"
                               required
                               class="bg-gray-50 border {{ $errors->has('violation_limit') ? 'border-red-500 text-red-900 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500' }} text-sm rounded-lg block w-full p-2.5 pr-14 transition">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-xs text-gray-400">
                            Strikes
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500">Number of violations before customer booking is blocked (1-100).</p>
                    @error('violation_limit')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Block Duration Days -->
                <div>
                    <label for="block_duration_days" class="block mb-2 text-sm font-medium text-gray-900">
                        Block Duration <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="number"
                               id="block_duration_days"
                               name="block_duration_days"
                               min="1"
                               max="365"
                               value="{{ old('block_duration_days', $company->block_duration_days ?? 7) }}"
                               required
                               class="bg-gray-50 border {{ $errors->has('block_duration_days') ? 'border-red-500 text-red-900 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500' }} text-sm rounded-lg block w-full p-2.5 pr-14 transition">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-xs text-gray-400">
                            Days
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500">How many days a customer remains blocked upon reaching the violation limit (1-365).</p>
                    @error('block_duration_days')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <section class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold">WhatsApp bookings</h2>
            <p class="mt-1 text-sm text-gray-500">Configure your salon number. Live WhatsApp messaging is not connected yet.</p>
            <input type="hidden" name="whatsapp_enabled" value="0">
            <label class="mt-4 flex items-center gap-2">
                <input type="checkbox" name="whatsapp_enabled" value="1" @checked(old('whatsapp_enabled', $company->whatsapp_enabled))>
                <span>Enable WhatsApp bookings</span>
            </label>
            @error('whatsapp_enabled')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            <label for="whatsapp_phone_number" class="mt-4 block text-sm font-medium">Salon WhatsApp number</label>
            <input id="whatsapp_phone_number" name="whatsapp_phone_number" type="tel" maxlength="30"
                   value="{{ old('whatsapp_phone_number', $company->whatsapp_phone_number) }}" placeholder="+90 555 123 4567"
                   class="mt-2 w-full rounded-lg border border-gray-300 p-2.5">
            @error('whatsapp_phone_number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </section>

        <!-- Submit & Actions Bar -->
        <div class="flex items-center justify-end space-x-3 pt-2">
            <a href="{{ route('company.dashboard') }}" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-200 transition">
                Cancel
            </a>
            @if($company->status !== 'suspended')
                <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition">
                    Save Settings
                </button>
            @else
                <button type="button" disabled class="px-6 py-2.5 text-sm font-medium text-gray-400 bg-gray-200 rounded-lg cursor-not-allowed">
                    Locked (Suspended)
                </button>
            @endif
        </div>
    </form>
</x-layouts.company>
