<x-layouts.company>
    <x-slot:title>Staff &amp; Employees - Barbar SaaS</x-slot:title>

    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Staff &amp; Employee Management</h1>
            <p class="text-sm text-gray-500 mt-1">Manage barber salon specialists, login accounts, and active service statuses.</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('company.dashboard') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-200 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Dashboard
            </a>
            @if($company->status !== 'suspended')
                <a href="{{ route('company.employees.create') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Employee
                </a>
            @endif
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
                <span class="block text-xs mt-0.5 text-red-700">Please contact platform administration. Staff creation and status updates are locked.</span>
            </div>
        </div>
    @endif

    <!-- One-Time Credentials Display Card -->
    @if($creds)
        @php
            $allDetails = "Salon Login Information\n\nCompany Code: {$creds['company_code']}\nUsername: {$creds['username']}\nTemporary Password: {$creds['password']}";
        @endphp
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-xl p-6 mb-8 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-blue-100 pb-4 mb-5">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Employee Login Information</h2>
                        <p class="text-xs text-blue-800 font-medium">New credentials generated for <span class="font-bold">{{ $creds['name'] }}</span>.</p>
                    </div>
                </div>
                <button type="button" onclick="copyText({{ Illuminate\Support\Js::from($allDetails) }}, this)"
                        class="inline-flex items-center justify-center px-4 py-2.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                    </svg>
                    Copy All Login Details
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- Company Code -->
                <div class="bg-white p-4 rounded-lg border border-blue-100 shadow-xs flex flex-col justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Company Code</span>
                    <div class="my-2 flex items-baseline justify-between">
                        <span class="font-mono text-base font-bold text-blue-700">{{ $creds['company_code'] }}</span>
                        <button type="button" onclick="copyText({{ Illuminate\Support\Js::from($creds['company_code']) }}, this)"
                                class="text-xs text-blue-600 hover:text-blue-800 font-medium px-2 py-1 bg-blue-50 hover:bg-blue-100 rounded transition">
                            Copy Company Code
                        </button>
                    </div>
                </div>

                <!-- Username -->
                <div class="bg-white p-4 rounded-lg border border-blue-100 shadow-xs flex flex-col justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Username</span>
                    <div class="my-2 flex items-baseline justify-between">
                        <span class="font-mono text-base font-bold text-gray-900">{{ $creds['username'] }}</span>
                        <button type="button" onclick="copyText({{ Illuminate\Support\Js::from($creds['username']) }}, this)"
                                class="text-xs text-blue-600 hover:text-blue-800 font-medium px-2 py-1 bg-blue-50 hover:bg-blue-100 rounded transition">
                            Copy Username
                        </button>
                    </div>
                </div>

                <!-- Temporary Password -->
                <div class="bg-white p-4 rounded-lg border border-blue-100 shadow-xs flex flex-col justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Temporary Password</span>
                    <div class="my-2 flex items-baseline justify-between">
                        <span class="font-mono text-base font-bold text-gray-900">{{ $creds['password'] }}</span>
                        <button type="button" onclick="copyText({{ Illuminate\Support\Js::from($creds['password']) }}, this)"
                                class="text-xs text-blue-600 hover:text-blue-800 font-medium px-2 py-1 bg-blue-50 hover:bg-blue-100 rounded transition">
                            Copy Password
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-3">
                <svg class="w-4 h-4 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <span><strong>Notice:</strong> Save or send these login details now. The temporary password will not be displayed again.</span>
            </div>
        </div>
    @endif

    <!-- Context Banner: Salon Public Code Identifier -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-xs">
                SLN
            </div>
            <div>
                <span class="text-xs text-gray-500">Salon Identifier:</span>
                <span class="ml-1 text-sm font-mono font-bold text-blue-600">{{ $company->code }}</span>
                <span class="text-gray-400 mx-2">&bull;</span>
                <span class="text-xs text-gray-600">{{ $company->name }}</span>
            </div>
        </div>
        <div class="text-xs text-gray-500">
            Employees use this <strong>Company Code</strong> + their <strong>Username</strong> to sign in.
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6">
        <form method="GET" action="{{ route('company.employees.index') }}" class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <!-- Search by Name or Username -->
            <div class="relative flex-1 max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Search by name or username..."
                       class="block w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Status Filter -->
                <div class="flex items-center space-x-2">
                    <label for="status" class="text-xs font-semibold text-gray-600">Status:</label>
                    <select id="status"
                            name="status"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 py-2 px-3">
                        <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive Only</option>
                    </select>
                </div>

                <!-- Submit Filter Button -->
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    Filter
                </button>

                <!-- Reset Filter Link -->
                @if(request('search') || (request('status') && request('status') !== 'all'))
                    <a href="{{ route('company.employees.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-500 hover:text-red-600 transition">
                        Clear Filters
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Employee Table / List -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        @if($employees->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                        <tr>
                            <th scope="col" class="px-6 py-3.5">Employee Name</th>
                            <th scope="col" class="px-6 py-3.5">Username</th>
                            <th scope="col" class="px-6 py-3.5">Phone</th>
                            <th scope="col" class="px-6 py-3.5">Status</th>
                            <th scope="col" class="px-6 py-3.5">Created Date</th>
                            <th scope="col" class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($employees as $employee)
                            <tr class="hover:bg-gray-50/75 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm">
                                            {{ strtoupper(substr($employee->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $employee->name }}</div>
                                            @if($employee->must_change_password)
                                                <span class="inline-flex items-center text-[10px] text-amber-600">
                                                    Password reset required
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-700 text-xs">
                                    {{ $employee->username }}
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    {{ $employee->phone ?: '—' }}
                                </td>
                                <td class="px-6 py-4">
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
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500">
                                    {{ $employee->created_at->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if($company->status !== 'suspended')
                                        <div class="inline-flex items-center space-x-2">
                                            <!-- Schedule Link -->
                                            <a href="{{ route('company.employees.schedule.edit', $employee->id) }}"
                                               class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-md transition"
                                               title="Configure Working Availability">
                                                Schedule
                                            </a>

                                            <!-- Edit Link -->
                                            <a href="{{ route('company.employees.edit', $employee->id) }}"
                                               class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-md transition">
                                                Edit
                                            </a>

                                            <!-- Status Toggle Form with confirmation -->
                                            @if($employee->active)
                                                <form action="{{ route('company.employees.status', $employee->id) }}"
                                                      method="POST"
                                                      class="inline"
                                                      onsubmit="return confirm('Are you sure you want to deactivate {{ addslashes($employee->name) }}? Deactivated employees cannot perform salon shifts.');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="active" value="0">
                                                    <button type="submit"
                                                            class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-md transition">
                                                        Deactivate
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('company.employees.status', $employee->id) }}"
                                                      method="POST"
                                                      class="inline"
                                                      onsubmit="return confirm('Are you sure you want to activate {{ addslashes($employee->name) }}?');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="active" value="1">
                                                    <button type="submit"
                                                            class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-green-700 bg-green-50 hover:bg-green-100 rounded-md transition">
                                                        Activate
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 italic">Locked</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($employees->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $employees->links() }}
                </div>
            @endif
        @else
            <!-- Empty State -->
            <div class="text-center py-12 px-4">
                <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                @if(request('search') || (request('status') && request('status') !== 'all'))
                    <h3 class="text-base font-semibold text-gray-900">No employees found matching your criteria</h3>
                    <p class="text-sm text-gray-500 mt-1 max-w-sm mx-auto">Try adjusting your search terms or status filter to see other staff members.</p>
                    <div class="mt-5">
                        <a href="{{ route('company.employees.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                            Clear Filters
                        </a>
                    </div>
                @else
                    <h3 class="text-base font-semibold text-gray-900">No employees registered yet</h3>
                    <p class="text-sm text-gray-500 mt-1 max-w-sm mx-auto">Get started by creating staff accounts for your salon barbers and service specialists.</p>
                    @if($company->status !== 'suspended')
                        <div class="mt-5">
                            <a href="{{ route('company.employees.create') }}" class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Your First Employee
                            </a>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </div>

    <!-- Script for Clipboard Copy -->
    <script>
        function copyText(text, btn) {
            navigator.clipboard.writeText(text).then(function() {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = 'Copied!';
                btn.classList.add('bg-green-100', 'text-green-800');
                setTimeout(function() {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('bg-green-100', 'text-green-800');
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
            });
        }
    </script>
</x-layouts.company>
