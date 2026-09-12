<x-layouts.company>
    <x-slot:title>Customer Directory - Barbar SaaS</x-slot:title>

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Customer Directory</h1>
            <p class="text-sm text-gray-500 mt-1">Manage salon client records, view behavioral violation counts, and manage active booking restrictions.</p>
        </div>
    </div>

    @if(session('status'))
        <div class="p-4 mb-6 text-sm text-green-800 rounded-xl bg-green-50 border border-green-200 flex items-center space-x-3">
            <svg class="w-5 h-5 flex-shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="font-medium">{{ session('status') }}</span>
        </div>
    @endif

    <!-- Search & Filter Toolbar -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6">
        <form method="GET" action="{{ route('company.customers.index') }}" class="flex flex-col sm:flex-row gap-3">
            <!-- Search Query (Name or Phone) -->
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search by client name or phone number..."
                       class="block w-full pl-9 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Status Filter -->
            <div class="w-full sm:w-48">
                <select name="status" class="block w-full py-2 px-3 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All Clients</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                    <option value="blocked" {{ request('status') === 'blocked' ? 'selected' : '' }}>Currently Blocked</option>
                    <option value="not_blocked" {{ request('status') === 'not_blocked' ? 'selected' : '' }}>Not Blocked</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-2">
                <button type="submit" class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-slate-800 hover:bg-slate-900 rounded-lg transition">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'status']) && (request('search') !== null || request('status') !== 'all'))
                    <a href="{{ route('company.customers.index') }}" class="w-full sm:w-auto px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg text-center transition">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Customers Table -->
    @if($customers->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 text-blue-600 mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900">No Customers Found</h3>
            <p class="text-sm text-gray-500 mt-1 max-w-md mx-auto">
                @if(request('search') || request('status'))
                    No clients match your filter criteria. Try broadening your search.
                @else
                    Customer records are automatically captured when clients book appointments or interact via WhatsApp.
                @endif
            </p>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th scope="col" class="px-6 py-3.5 font-bold">Client Name</th>
                            <th scope="col" class="px-6 py-3.5 font-bold">Phone (WhatsApp)</th>
                            <th scope="col" class="px-6 py-3.5 font-bold">Block Status</th>
                            <th scope="col" class="px-6 py-3.5 font-bold">Violations</th>
                            <th scope="col" class="px-6 py-3.5 font-bold">Registered</th>
                            <th scope="col" class="px-6 py-3.5 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($customers as $customer)
                            @php
                                $activeBlock = $customer->activeBlock();
                            @endphp
                            <tr class="hover:bg-gray-50/75 transition">
                                <!-- Name -->
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-900 text-sm">{{ $customer->name }}</div>
                                    @if($customer->notes)
                                        <div class="text-xs text-gray-400 mt-0.5 line-clamp-1 max-w-xs">{{ $customer->notes }}</div>
                                    @endif
                                </td>

                                <!-- Phone -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-mono text-gray-800 text-xs font-semibold">{{ $customer->phone }}</span>
                                </td>

                                <!-- Block Status -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($activeBlock)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span>
                                            Blocked
                                        </span>
                                        @if($activeBlock->ends_at)
                                            <span class="text-[11px] text-red-600 block mt-0.5">Until {{ $activeBlock->ends_at->format('M d, H:i') }}</span>
                                        @else
                                            <span class="text-[11px] text-red-600 block mt-0.5">Indefinite</span>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span>
                                            Clear
                                        </span>
                                    @endif
                                </td>

                                <!-- Violations Count -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $customer->violations->count() > 0 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $customer->violations->count() }}
                                    </span>
                                </td>

                                <!-- Registered Date -->
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                    {{ $customer->created_at->format('M d, Y') }}
                                </td>

                                <!-- Action Link -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <a href="{{ route('company.customers.show', $customer->id) }}"
                                       class="px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg transition">
                                        View Profile &rarr;
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($customers->hasPages())
                <div class="p-4 border-t border-gray-200 bg-gray-50">
                    {{ $customers->links() }}
                </div>
            @endif
        </div>
    @endif
</x-layouts.company>
