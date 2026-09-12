<x-layouts.admin>
    <x-slot:title>{{ $company->name }} - Barbar System Admin</x-slot:title>

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('admin.companies.index') }}" class="hover:text-blue-600">Companies</a>
                <span>/</span>
                <span class="font-mono font-medium text-gray-700">{{ $company->code }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $company->name }}</h1>
        </div>

        <div>
            <a href="{{ route('admin.companies.index') }}" class="inline-flex items-center text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 px-4 py-2 rounded-lg shadow-sm transition">
                &larr; Back to Companies
            </a>
        </div>
    </div>

    <!-- Company Information Grid (Read-Only Inspection) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Company Identity Card -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex justify-between items-start border-b border-gray-100 pb-4 mb-5">
                <div>
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Company Identity</span>
                    <h2 class="text-xl font-bold text-gray-900 mt-1">{{ $company->name }}</h2>
                </div>
                <div class="flex items-center space-x-3">
                    @if($company->status === 'active')
                        <span class="bg-green-100 text-green-800 text-xs font-semibold px-3 py-1 rounded-full">Active Status</span>
                    @elseif($company->status === 'pending')
                        <span class="bg-amber-100 text-amber-800 text-xs font-semibold px-3 py-1 rounded-full">Pending Review</span>
                    @else
                        <span class="bg-red-100 text-red-800 text-xs font-semibold px-3 py-1 rounded-full">{{ ucfirst($company->status) }}</span>
                    @endif

                    @if($company->status === 'pending')
                        <form action="{{ route('admin.companies.status', $company) }}" method="POST" class="inline"
                              onsubmit="return confirm('Activate this company?');">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="inline-flex items-center text-xs font-semibold text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 px-3 py-1 rounded-lg transition">
                                Activate
                            </button>
                        </form>

                        <form action="{{ route('admin.companies.status', $company) }}" method="POST" class="inline"
                              onsubmit="return confirm('Suspend this company? The company manager will lose access to protected operational functionality according to the current access policy.');">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="suspended">
                            <button type="submit" class="inline-flex items-center text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1 rounded-lg transition">
                                Suspend
                            </button>
                        </form>
                    @elseif($company->status === 'active')
                        <form action="{{ route('admin.companies.status', $company) }}" method="POST" class="inline"
                              onsubmit="return confirm('Suspend this company? The company manager will lose access to protected operational functionality according to the current access policy.');">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="suspended">
                            <button type="submit" class="inline-flex items-center text-xs font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-3 py-1 rounded-lg transition">
                                Suspend Company
                            </button>
                        </form>
                    @elseif($company->status === 'suspended')
                        <form action="{{ route('admin.companies.status', $company) }}" method="POST" class="inline"
                              onsubmit="return confirm('Activate this company?');">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="inline-flex items-center text-xs font-semibold text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 px-3 py-1 rounded-lg transition">
                                Activate Company
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Public Company Code</dt>
                    <dd class="mt-1 font-mono text-base font-bold text-blue-600">{{ $company->code }}</dd>
                </div>

                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Salon Phone</dt>
                    <dd class="mt-1 font-medium text-gray-900">{{ $company->phone }}</dd>
                </div>

                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Registration Date</dt>
                    <dd class="mt-1 text-gray-900">{{ $company->created_at->format('F d, Y \a\t H:i') }} ({{ $company->created_at->diffForHumans() }})</dd>
                </div>

                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Last Record Update</dt>
                    <dd class="mt-1 text-gray-900">{{ $company->updated_at->format('F d, Y \a\t H:i') }}</dd>
                </div>
            </dl>
        </div>

        <!-- Assigned Manager Details Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="border-b border-gray-100 pb-4 mb-5">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Assigned Salon Manager</span>
                <h3 class="text-lg font-bold text-gray-900 mt-1">
                    {{ $company->manager?->name ?? 'No Manager Assigned' }}
                </h3>
            </div>

            @if($company->manager)
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Email Address</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $company->manager->email }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">User Role</dt>
                        <dd class="mt-1">
                            <span class="bg-purple-100 text-purple-800 text-xs font-semibold px-2 py-0.5 rounded">
                                {{ $company->manager->role }}
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Account Created</dt>
                        <dd class="mt-1 text-xs text-gray-600">{{ $company->manager->created_at->format('M d, Y') }}</dd>
                    </div>
                </dl>
            @else
                <p class="text-sm text-gray-500">This company does not currently have an active manager user attached.</p>
            @endif
        </div>
    </div>

    <!-- Related Audit Activity -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-200">
            <h3 class="text-base font-bold text-gray-900">Company Audit History</h3>
            <p class="text-xs text-gray-500 mt-0.5">Recorded log entries related to this salon organization.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th scope="col" class="px-6 py-3">Timestamp</th>
                        <th scope="col" class="px-6 py-3">Action</th>
                        <th scope="col" class="px-6 py-3">Description</th>
                        <th scope="col" class="px-6 py-3">Actor / User</th>
                        <th scope="col" class="px-6 py-3">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($company->auditLogs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3.5 text-xs text-gray-500 whitespace-nowrap">
                                {{ $log->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-6 py-3.5 font-mono text-xs font-semibold text-gray-900">
                                {{ $log->action }}
                            </td>
                            <td class="px-6 py-3.5 text-xs text-gray-700">
                                {{ $log->description ?? '-' }}
                            </td>
                            <td class="px-6 py-3.5 text-xs text-gray-600">
                                {{ $log->user?->name ?? 'System' }}
                            </td>
                            <td class="px-6 py-3.5 text-xs font-mono text-gray-400">
                                {{ $log->ip_address ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500 text-xs">
                                No audit log entries recorded for this company yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
