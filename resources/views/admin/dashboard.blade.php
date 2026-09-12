<x-layouts.admin>
    <x-slot:title>Dashboard - Barbar System Admin</x-slot:title>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">System Admin Dashboard</h1>
        <p class="text-sm text-gray-500 mt-1">Overview of SaaS platform metrics, salons, and recent activities.</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Total Companies -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Salons</p>
                    <h3 class="text-2xl font-extrabold text-gray-900 mt-1">{{ $stats['total_companies'] }}</h3>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4zm3 1h6v2H7V5zm6 4H7v2h6V9zm-6 4h6v2H7v-2z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-3 text-xs text-gray-500">All registered companies in platform</div>
        </div>

        <!-- Active Salons -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-green-600 uppercase tracking-wider">Active Salons</p>
                    <h3 class="text-2xl font-extrabold text-gray-900 mt-1">{{ $stats['active_companies'] }}</h3>
                </div>
                <div class="w-10 h-10 rounded-lg bg-green-50 text-green-600 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-3 text-xs text-gray-500">Live operational salons</div>
        </div>

        <!-- Pending Review -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Pending Review</p>
                    <h3 class="text-2xl font-extrabold text-gray-900 mt-1">{{ $stats['pending_companies'] }}</h3>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-3 text-xs text-gray-500">Awaiting status review</div>
        </div>

        <!-- Registered Managers -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-purple-600 uppercase tracking-wider">Managers</p>
                    <h3 class="text-2xl font-extrabold text-gray-900 mt-1">{{ $stats['total_managers'] }}</h3>
                </div>
                <div class="w-10 h-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-3 text-xs text-gray-500">Assigned salon managers</div>
        </div>
    </div>

    <!-- Recent Companies & Audit Logs -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Companies (2 Cols) -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="p-5 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-base font-bold text-gray-900">Recent Companies</h2>
                <a href="{{ route('admin.companies.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">
                    View All &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-5 py-3">Code</th>
                            <th scope="col" class="px-5 py-3">Salon Name</th>
                            <th scope="col" class="px-5 py-3">Manager</th>
                            <th scope="col" class="px-5 py-3">Status</th>
                            <th scope="col" class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($recentCompanies as $comp)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3.5 font-mono text-xs font-semibold text-gray-900">
                                    {{ $comp->code }}
                                </td>
                                <td class="px-5 py-3.5 font-medium text-gray-900">
                                    {{ $comp->name }}
                                </td>
                                <td class="px-5 py-3.5 text-xs text-gray-600">
                                    {{ $comp->manager?->name ?? 'None' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    @if($comp->status === 'active')
                                        <span class="bg-green-100 text-green-800 text-xs font-semibold px-2 py-0.5 rounded">Active</span>
                                    @elseif($comp->status === 'pending')
                                        <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded">Pending</span>
                                    @else
                                        <span class="bg-red-100 text-red-800 text-xs font-semibold px-2 py-0.5 rounded">{{ ucfirst($comp->status) }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('admin.companies.show', $comp) }}" class="text-xs font-semibold text-blue-600 hover:underline">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-6 text-center text-gray-500">
                                    No companies registered yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Activity Feed (1 Col) -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="p-5 border-b border-gray-200">
                <h2 class="text-base font-bold text-gray-900">Recent Audit Activity</h2>
            </div>

            <div class="p-5">
                <ol class="relative border-l border-gray-200 space-y-6">
                    @forelse($recentAuditLogs as $log)
                        <li class="ml-4">
                            <div class="absolute w-3 h-3 bg-blue-600 rounded-full mt-1.5 -left-1.5 border border-white"></div>
                            <time class="mb-1 text-xs font-normal text-gray-400">
                                {{ $log->created_at->diffForHumans() }}
                            </time>
                            <h3 class="text-xs font-semibold text-gray-900">
                                {{ $log->action }}
                            </h3>
                            <p class="text-xs font-normal text-gray-500 mt-1">
                                {{ $log->description ?? 'No extra details recorded.' }}
                            </p>
                        </li>
                    @empty
                        <li class="ml-4 text-xs text-gray-500">No activity recorded yet.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>
</x-layouts.admin>
