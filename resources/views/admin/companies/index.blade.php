<x-layouts.admin>
    <x-slot:title>Companies - Barbar System Admin</x-slot:title>

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Salons & Companies</h1>
            <p class="text-sm text-gray-500 mt-1">Directory of all salon organizations in the platform.</p>
        </div>
    </div>

    <!-- Filters & Search Form -->
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm mb-6">
        <form method="GET" action="{{ route('admin.companies.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label for="search" class="sr-only">Search</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5"
                        placeholder="Search by company name, code (e.g. SLN-), phone, or manager...">
                </div>
            </div>

            <div class="flex gap-2">
                <select name="status" id="status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                </select>

                <button type="submit" class="text-white bg-blue-600 hover:bg-blue-700 font-medium rounded-lg text-sm px-4 py-2.5 text-center shadow-sm">
                    Filter
                </button>

                @if(request('search') || request('status'))
                    <a href="{{ route('admin.companies.index') }}" class="text-gray-600 bg-gray-100 hover:bg-gray-200 font-medium rounded-lg text-sm px-3 py-2.5 flex items-center">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Companies Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th scope="col" class="px-6 py-3">Public Code</th>
                        <th scope="col" class="px-6 py-3">Salon / Company</th>
                        <th scope="col" class="px-6 py-3">Manager</th>
                        <th scope="col" class="px-6 py-3">Phone</th>
                        <th scope="col" class="px-6 py-3">Status</th>
                        <th scope="col" class="px-6 py-3">Registered Date</th>
                        <th scope="col" class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($companies as $company)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-mono text-xs font-bold text-gray-900">
                                {{ $company->code }}
                            </td>
                            <td class="px-6 py-4 font-semibold text-gray-900">
                                {{ $company->name }}
                            </td>
                            <td class="px-6 py-4">
                                @if($company->manager)
                                    <div class="font-medium text-gray-900">{{ $company->manager->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $company->manager->email }}</div>
                                @else
                                    <span class="text-xs text-gray-400">Unassigned</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-600">
                                {{ $company->phone }}
                            </td>
                            <td class="px-6 py-4">
                                @if($company->status === 'active')
                                    <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">Active</span>
                                @elseif($company->status === 'pending')
                                    <span class="bg-amber-100 text-amber-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">Pending</span>
                                @else
                                    <span class="bg-red-100 text-red-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">{{ ucfirst($company->status) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-500">
                                {{ $company->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center space-x-2">
                                    @if($company->status === 'pending')
                                        <form action="{{ route('admin.companies.status', $company) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Activate this company?');">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="inline-flex items-center text-xs font-semibold text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 px-2.5 py-1.5 rounded-lg transition">
                                                Activate
                                            </button>
                                        </form>

                                        <form action="{{ route('admin.companies.status', $company) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Suspend this company? The company manager will lose access to protected operational functionality according to the current access policy.');">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="suspended">
                                            <button type="submit" class="inline-flex items-center text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 px-2.5 py-1.5 rounded-lg transition">
                                                Suspend
                                            </button>
                                        </form>
                                    @elseif($company->status === 'active')
                                        <form action="{{ route('admin.companies.status', $company) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Suspend this company? The company manager will lose access to protected operational functionality according to the current access policy.');">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="suspended">
                                            <button type="submit" class="inline-flex items-center text-xs font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-2.5 py-1.5 rounded-lg transition">
                                                Suspend
                                            </button>
                                        </form>
                                    @elseif($company->status === 'suspended')
                                        <form action="{{ route('admin.companies.status', $company) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Activate this company?');">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="inline-flex items-center text-xs font-semibold text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 px-2.5 py-1.5 rounded-lg transition">
                                                Activate
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('admin.companies.show', $company) }}" class="inline-flex items-center text-xs font-semibold text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition">
                                        Details &rarr;
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <p class="text-base font-medium text-gray-900">No companies found</p>
                                <p class="text-xs text-gray-500 mt-1">Try adjusting your search filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($companies->hasPages())
            <div class="p-4 border-t border-gray-200">
                {{ $companies->links() }}
            </div>
        @endif
    </div>
</x-layouts.admin>
