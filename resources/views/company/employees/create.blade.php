<x-layouts.company>
    <x-slot:title>Add Employee - Barbar SaaS</x-slot:title>

    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Add New Salon Employee</h1>
            <p class="text-sm text-gray-500 mt-1">Create a staff account with initial credentials for your salon.</p>
        </div>
        <div>
            <a href="{{ route('company.employees.index') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-200 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Employees
            </a>
        </div>
    </div>

    <!-- Salon Info Banner -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-xs">
                {{ strtoupper(substr($company->name, 0, 1)) }}
            </div>
            <div>
                <p class="text-xs font-semibold text-blue-900">Assigning to Salon: {{ $company->name }}</p>
                <p class="text-[11px] text-blue-700">Company Code: <span class="font-mono font-bold">{{ $company->code }}</span></p>
            </div>
        </div>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
            Initial Status: Active
        </span>
    </div>

    <!-- Employee Create Form -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8 max-w-2xl">
        <form action="{{ route('company.employees.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Full Name -->
            <div>
                <label for="name" class="block text-sm font-semibold text-gray-700 mb-1">
                    Employee Full Name <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       id="name"
                       name="name"
                       value="{{ old('name') }}"
                       required
                       placeholder="e.g. John Barber"
                       class="block w-full px-4 py-2.5 border rounded-lg text-sm transition focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 bg-red-50/50 @else border-gray-300 @enderror">
                @error('name')
                    <p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p>
                @enderror
                <p class="text-xs text-gray-400 mt-1">Displayed to clients and staff on schedules.</p>
            </div>

            <!-- Username -->
            <div>
                <label for="username" class="block text-sm font-semibold text-gray-700 mb-1">
                    Username / Login ID <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       id="username"
                       name="username"
                       value="{{ old('username') }}"
                       required
                       placeholder="e.g. john_barber"
                       class="block w-full px-4 py-2.5 border rounded-lg text-sm font-mono transition focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('username') border-red-500 bg-red-50/50 @else border-gray-300 @enderror">
                @error('username')
                    <p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p>
                @enderror
                <p class="text-xs text-gray-400 mt-1">Unique within this salon. Letters, numbers, dashes, and underscores only (min 3 chars).</p>
            </div>

            <!-- Phone -->
            <div>
                <label for="phone" class="block text-sm font-semibold text-gray-700 mb-1">
                    Phone Number <span class="text-xs font-normal text-gray-400">(Optional)</span>
                </label>
                <input type="text"
                       id="phone"
                       name="phone"
                       value="{{ old('phone') }}"
                       placeholder="e.g. +1 555 123 4567"
                       class="block w-full px-4 py-2.5 border rounded-lg text-sm transition focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 bg-red-50/50 @else border-gray-300 @enderror">
                @error('phone')
                    <p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Initial Password -->
            <div>
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">
                    Temporary Password <span class="text-red-500">*</span>
                </label>
                <input type="password"
                       id="password"
                       name="password"
                       required
                       placeholder="Minimum 8 characters"
                       class="block w-full px-4 py-2.5 border rounded-lg text-sm transition focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('password') border-red-500 bg-red-50/50 @else border-gray-300 @enderror">
                @error('password')
                    <p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p>
                @enderror
                <p class="text-xs text-gray-500 mt-1.5">
                    <span class="font-medium text-gray-700">Notice:</span> Employee will be prompted to change this temporary password upon initial account access.
                </p>
            </div>

            <!-- Form Actions -->
            <div class="pt-4 border-t border-gray-200 flex items-center justify-end space-x-3">
                <a href="{{ route('company.employees.index') }}" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm focus:ring-4 focus:ring-blue-300 transition">
                    Create Employee
                </button>
            </div>
        </form>
    </div>
</x-layouts.company>
