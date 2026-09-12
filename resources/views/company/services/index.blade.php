<x-layouts.company>
    <x-slot:title>Services & Catalog - Barbar SaaS</x-slot:title>

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Services &amp; Catalog</h1>
            <p class="text-sm text-gray-500 mt-1">Configure salon services, pricing, durations, and assign qualified staff members.</p>
        </div>
        <div>
            @if($company->status === \App\Models\Company::STATUS_SUSPENDED)
                <button type="button" disabled class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-gray-400 cursor-not-allowed rounded-lg">
                    + Add New Service
                </button>
            @else
                <a href="{{ route('company.services.create') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 shadow-sm transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    + Add New Service
                </a>
            @endif
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if(session('status'))
        <div class="p-4 mb-6 text-sm text-green-800 rounded-xl bg-green-50 border border-green-200 flex items-center space-x-3">
            <svg class="w-5 h-5 flex-shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="font-medium">{{ session('status') }}</span>
        </div>
    @endif

    @if($company->status === \App\Models\Company::STATUS_SUSPENDED)
        <div class="p-4 mb-6 text-sm text-red-800 rounded-xl bg-red-50 border border-red-200 flex items-center space-x-3">
            <svg class="w-5 h-5 flex-shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
                <span class="font-bold">Salon Account Suspended:</span> Service modifications and staff reassignments are currently locked.
            </div>
        </div>
    @endif

    <!-- Filter & Search Toolbar -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6">
        <form method="GET" action="{{ route('company.services.index') }}" class="flex flex-col sm:flex-row gap-3">
            <!-- Search Query -->
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search by service name..."
                       class="block w-full pl-9 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Status Filter -->
            <div class="w-full sm:w-48">
                <select name="status" class="block w-full py-2 px-3 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive Only</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-2">
                <button type="submit" class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-slate-800 hover:bg-slate-900 rounded-lg transition">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'status']) && (request('search') !== null || request('status') !== 'all'))
                    <a href="{{ route('company.services.index') }}" class="w-full sm:w-auto px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg text-center transition">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Services Table or Empty State -->
    @if($services->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 text-blue-600 mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879a3 3 0 11-4.242-4.242L10.758 4.758a3 3 0 014.242 4.242L12 12z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900">No Services Found</h3>
            <p class="text-sm text-gray-500 mt-1 max-w-md mx-auto">
                @if(request('search') || request('status'))
                    No services match your active search or filter criteria. Try adjusting your query.
                @else
                    You have not registered any salon services yet. Add your first service to start offering appointments.
                @endif
            </p>
            @if(!request('search') && $company->status !== \App\Models\Company::STATUS_SUSPENDED)
                <div class="mt-6">
                    <a href="{{ route('company.services.create') }}" class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Create First Service
                    </a>
                </div>
            @endif
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th scope="col" class="px-6 py-3.5 font-bold">Service &amp; Description</th>
                            <th scope="col" class="px-6 py-3.5 font-bold">Price</th>
                            <th scope="col" class="px-6 py-3.5 font-bold">Duration</th>
                            <th scope="col" class="px-6 py-3.5 font-bold">Assigned Staff</th>
                            <th scope="col" class="px-6 py-3.5 font-bold">Status</th>
                            <th scope="col" class="px-6 py-3.5 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($services as $service)
                            <tr class="hover:bg-gray-50/75 transition">
                                <!-- Service Name & Description -->
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-900 text-sm">{{ $service->name }}</div>
                                    @if($service->description)
                                        <div class="text-xs text-gray-500 mt-0.5 line-clamp-1 max-w-sm">{{ $service->description }}</div>
                                    @endif
                                </td>

                                <!-- Price -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-bold text-gray-900 font-mono">{{ $service->formatted_price }}</span>
                                </td>

                                <!-- Duration -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="inline-flex items-center text-xs font-semibold text-gray-700 bg-gray-100 px-2.5 py-1 rounded-md">
                                        <svg class="w-3.5 h-3.5 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ $service->duration_minutes }} mins
                                    </div>
                                </td>

                                <!-- Assigned Employees -->
                                <td class="px-6 py-4">
                                    @if($service->employees->isNotEmpty())
                                        <div class="flex flex-wrap gap-1.5 max-w-xs">
                                            @foreach($service->employees->take(3) as $employee)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium {{ $employee->active ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-gray-100 text-gray-500 border border-gray-200 line-through' }}">
                                                    {{ $employee->name }}
                                                </span>
                                            @endforeach
                                            @if($service->employees->count() > 3)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-bold bg-gray-100 text-gray-600">
                                                    +{{ $service->employees->count() - 3 }} more
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="inline-flex items-center text-xs text-amber-600 font-medium">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                            </svg>
                                            No staff assigned
                                        </span>
                                    @endif
                                </td>

                                <!-- Status Badge -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($service->active)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span>
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400 mr-1.5"></span>
                                            Inactive
                                        </span>
                                    @endif
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <div class="flex items-center justify-end space-x-2">
                                        @if($company->status === \App\Models\Company::STATUS_SUSPENDED)
                                            <span class="text-xs text-gray-400 italic">Locked</span>
                                        @else
                                            <a href="{{ route('company.services.schedule.edit', $service->id) }}"
                                               class="px-2.5 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg transition"
                                               title="Configure Weekly Schedule">
                                                Schedule
                                            </a>

                                            <a href="{{ route('company.services.edit', $service->id) }}"
                                               class="px-2.5 py-1.5 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 rounded-lg transition">
                                                Edit
                                            </a>

                                            <!-- Toggle Status Button -->
                                            <form action="{{ route('company.services.status', $service->id) }}" method="POST" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="active" value="{{ $service->active ? '0' : '1' }}">
                                                <button type="submit"
                                                        onclick="return confirm('Are you sure you want to {{ $service->active ? 'deactivate' : 'activate' }} \'{{ addslashes($service->name) }}\'?')"
                                                        class="px-2.5 py-1.5 text-xs font-medium rounded-lg transition border {{ $service->active ? 'text-amber-700 bg-amber-50 hover:bg-amber-100 border-amber-200' : 'text-green-700 bg-green-50 hover:bg-green-100 border-green-200' }}">
                                                    {{ $service->active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            @if($services->hasPages())
                <div class="p-4 border-t border-gray-200 bg-gray-50">
                    {{ $services->links() }}
                </div>
            @endif
        </div>
    @endif
</x-layouts.company>
