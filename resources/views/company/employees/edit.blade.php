<x-layouts.company>
    <x-slot:title>Edit Employee - Barbar SaaS</x-slot:title>

    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Edit Employee Details</h1>
            <p class="text-sm text-gray-500 mt-1">Update profile information for {{ $employee->name }}.</p>
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

    <!-- Employee Profile Summary Card -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6 max-w-2xl">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-lg">
                    {{ strtoupper(substr($employee->name, 0, 1)) }}
                </div>
                <div>
                    <h2 class="text-base font-bold text-gray-900">{{ $employee->name }}</h2>
                    <div class="flex items-center space-x-2 text-xs text-gray-500 mt-0.5">
                        <span class="font-mono">@<span class="text-gray-700">{{ $employee->username }}</span></span>
                        <span>&bull;</span>
                        <span>Registered {{ $employee->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <span class="text-xs text-gray-500">Status:</span>
                @if($employee->active)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span>
                        Active
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 mr-1.5"></span>
                        Inactive
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Employee Edit Form -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8 max-w-2xl">
        <form action="{{ route('company.employees.update', $employee->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Full Name -->
            <div>
                <label for="name" class="block text-sm font-semibold text-gray-700 mb-1">
                    Employee Full Name <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       id="name"
                       name="name"
                       value="{{ old('name', $employee->name) }}"
                       required
                       class="block w-full px-4 py-2.5 border rounded-lg text-sm transition focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 bg-red-50/50 @else border-gray-300 @enderror">
                @error('name')
                    <p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Username -->
            <div>
                <label for="username" class="block text-sm font-semibold text-gray-700 mb-1">
                    Username / Login ID <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       id="username"
                       name="username"
                       value="{{ old('username', $employee->username) }}"
                       required
                       class="block w-full px-4 py-2.5 border rounded-lg text-sm font-mono transition focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('username') border-red-500 bg-red-50/50 @else border-gray-300 @enderror">
                @error('username')
                    <p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p>
                @enderror
                <p class="text-xs text-gray-400 mt-1">Unique within this salon. Letters, numbers, dashes, and underscores only.</p>
            </div>

            <!-- Phone -->
            <div>
                <label for="phone" class="block text-sm font-semibold text-gray-700 mb-1">
                    Phone Number <span class="text-xs font-normal text-gray-400">(Optional)</span>
                </label>
                <input type="text"
                       id="phone"
                       name="phone"
                       value="{{ old('phone', $employee->phone) }}"
                       placeholder="e.g. +1 555 123 4567"
                       class="block w-full px-4 py-2.5 border rounded-lg text-sm transition focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 bg-red-50/50 @else border-gray-300 @enderror">
                @error('phone')
                    <p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Form Actions -->
            <div class="pt-4 border-t border-gray-200 flex items-center justify-end space-x-3">
                <a href="{{ route('company.employees.index') }}" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm focus:ring-4 focus:ring-blue-300 transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    <section class="mt-6 max-w-2xl rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
        <h2 class="text-lg font-bold text-gray-900">Employee login password</h2>
        <p class="mt-2 text-sm text-gray-600">Login at <a class="text-blue-700 underline" href="{{ route('employee.login') }}">Employee login</a> using company code {{ $company->code }} and the username above. Resetting the password signs out existing employee sessions and requires a new password at next login.</p>
        <form method="POST" action="{{ route('company.employees.password', $employee->id) }}" class="mt-5 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="reset_password" class="mb-2 block text-sm font-medium">Temporary password</label>
                <input type="password" id="reset_password" name="password" required minlength="8" maxlength="255" autocomplete="new-password" class="block w-full rounded-lg border border-gray-300 p-3 focus:ring-blue-500">
                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="reset_password_confirmation" class="mb-2 block text-sm font-medium">Confirm temporary password</label>
                <input type="password" id="reset_password_confirmation" name="password_confirmation" required autocomplete="new-password" class="block w-full rounded-lg border border-gray-300 p-3 focus:ring-blue-500">
            </div>
            <button class="min-h-11 rounded-lg bg-blue-600 px-5 py-3 font-medium text-white hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">Reset login password</button>
        </form>
    </section>

    <!-- Separate Status Management Card -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8 max-w-2xl mt-6">
        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-2">Account Status</h3>
        <p class="text-xs text-gray-500 mb-4 leading-relaxed">
            Deactivating an employee disables login and operational access and prevents new appointments, while preserving historical salon records.
        </p>

        @if($employee->active)
            <form action="{{ route('company.employees.status', $employee->id) }}"
                  method="POST"
                  onsubmit="return confirm('Are you sure you want to deactivate {{ addslashes($employee->name) }}?');">
                @csrf
                @method('PATCH')
                <input type="hidden" name="active" value="0">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg transition">
                    Deactivate This Employee
                </button>
            </form>
        @else
            <form action="{{ route('company.employees.status', $employee->id) }}"
                  method="POST"
                  onsubmit="return confirm('Are you sure you want to activate {{ addslashes($employee->name) }}?');">
                @csrf
                @method('PATCH')
                <input type="hidden" name="active" value="1">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-xs font-semibold text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg transition">
                    Activate This Employee
                </button>
            </form>
        @endif
    </div>
</x-layouts.company>
