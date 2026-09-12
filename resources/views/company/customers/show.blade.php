<x-layouts.company>
    <x-slot:title>Client Profile: {{ $customer->name }} - Barbar SaaS</x-slot:title>

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('company.customers.index') }}" class="hover:text-blue-600 transition">Customers</a>
                <span>/</span>
                <span>{{ $customer->name }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">{{ $customer->name }}</h1>
            <p class="text-sm font-mono text-gray-500 mt-0.5">{{ $customer->phone }}</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('company.customers.index') }}" class="inline-flex items-center px-3 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-xs">
                &larr; Back to Directory
            </a>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left: Customer Profile & Restriction Actions -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Restriction Status Card -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4">Booking Restriction Status</h2>

                <div class="mb-4">
                    @if($activeBlock)
                        <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-900">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-600"></span>
                                <span class="font-bold text-sm uppercase tracking-wide">Client is Blocked</span>
                            </div>
                            <div class="text-xs mt-2 space-y-1 text-red-800">
                                <div><span class="font-semibold">Reason:</span> {{ $activeBlock->reason }}</div>
                                <div><span class="font-semibold">Source:</span> {{ ucfirst($activeBlock->source) }}</div>
                                <div><span class="font-semibold">Started:</span> {{ $activeBlock->starts_at->format('M d, Y H:i') }}</div>
                                <div>
                                    <span class="font-semibold">Expires:</span>
                                    {{ $activeBlock->ends_at ? $activeBlock->ends_at->format('M d, Y H:i') : 'Indefinite' }}
                                </div>
                            </div>
                        </div>

                        <!-- Manual Unblock Form -->
                        @if($company->status !== 'suspended')
                            <form action="{{ route('company.customers.unblock', $customer->id) }}" method="POST" class="mt-4"
                                  onsubmit="return confirm('Are you sure you want to lift the active block for this client?');">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2 text-xs font-bold text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg transition">
                                    Lift Active Block (Unblock)
                                </button>
                            </form>
                        @endif
                    @else
                        <div class="p-4 rounded-xl bg-green-50 border border-green-200 text-green-900">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-green-600"></span>
                                <span class="font-bold text-sm uppercase tracking-wide">Clear (No Active Block)</span>
                            </div>
                            <p class="text-xs text-green-800 mt-1">This client is currently permitted to book appointments.</p>
                            <div class="mt-2 text-xs text-gray-600">
                                <span class="font-semibold">Active Cycle Violations:</span> {{ $qualifyingViolationsCount }} / {{ $company->violation_limit }}
                            </div>
                        </div>

                        <!-- Manual Block Trigger Button -->
                        @if($company->status !== 'suspended')
                            <div class="mt-4">
                                <button type="button" onclick="document.getElementById('manual-block-modal').classList.remove('hidden')"
                                        class="w-full px-4 py-2 text-xs font-bold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg transition">
                                    Manually Block Client...
                                </button>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Notes & Profile Form -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4">Internal Notes &amp; Status</h2>
                <form action="{{ route('company.customers.update', $customer->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="notes" class="block mb-1.5 text-xs font-bold text-gray-700 uppercase tracking-wider">Client Notes</label>
                        <textarea id="notes" name="notes" rows="4" placeholder="Enter private staff notes about this client..."
                                  class="block w-full p-2.5 text-xs text-gray-900 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">{{ old('notes', $customer->notes) }}</textarea>
                    </div>

                    <div class="mb-4">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="active" value="1" {{ old('active', $customer->active ? '1' : '0') == '1' ? 'checked' : '' }} class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            <span class="ml-3 text-xs font-bold text-gray-700 uppercase tracking-wider">Account Active</span>
                        </label>
                    </div>

                    <button type="submit" class="w-full px-4 py-2 text-xs font-bold text-white uppercase tracking-wider bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition">
                        Save Notes
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Violation & Block History -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Violations History -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-base font-bold text-gray-900">Behavioral Violations History</h2>
                    <span class="text-xs text-gray-500">{{ $customer->violations->count() }} Total Logged</span>
                </div>

                @if($customer->violations->isEmpty())
                    <div class="p-6 text-center text-xs text-gray-400 italic">
                        No behavioral violations recorded for this client.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left text-gray-500">
                            <thead class="text-[11px] text-gray-700 uppercase bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-2.5 font-bold">Occurred At</th>
                                    <th class="px-4 py-2.5 font-bold">Type</th>
                                    <th class="px-4 py-2.5 font-bold">Reason / Details</th>
                                    <th class="px-4 py-2.5 font-bold">Logged By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($customer->violations as $v)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-900 font-medium">
                                            {{ $v->occurred_at->format('M d, Y H:i') }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded font-bold uppercase text-[10px] {{ $v->type === 'no_show' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">
                                                {{ str_replace('_', ' ', $v->type) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $v->reason ?: '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">
                                            {{ $v->createdBy?->name ?? 'System' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- Block History -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-base font-bold text-gray-900">Booking Block History</h2>
                    <span class="text-xs text-gray-500">{{ $customer->blocks->count() }} Total Logged</span>
                </div>

                @if($customer->blocks->isEmpty())
                    <div class="p-6 text-center text-xs text-gray-400 italic">
                        No previous blocks recorded for this client.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left text-gray-500">
                            <thead class="text-[11px] text-gray-700 uppercase bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-2.5 font-bold">Status</th>
                                    <th class="px-4 py-2.5 font-bold">Source</th>
                                    <th class="px-4 py-2.5 font-bold">Reason</th>
                                    <th class="px-4 py-2.5 font-bold">Started</th>
                                    <th class="px-4 py-2.5 font-bold">Expires / Lifted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($customer->blocks as $block)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($block->status === 'ACTIVE')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded font-bold text-[10px] bg-red-100 text-red-800">ACTIVE</span>
                                            @elseif($block->status === 'LIFTED')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded font-bold text-[10px] bg-blue-100 text-blue-800">LIFTED</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded font-bold text-[10px] bg-gray-100 text-gray-600">EXPIRED</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 capitalize text-gray-700 font-medium">
                                            {{ $block->source }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 max-w-xs">
                                            {{ $block->reason }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                            {{ $block->starts_at->format('M d, Y H:i') }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                            @if($block->lifted_at)
                                                <span class="text-blue-600 font-semibold">Lifted {{ $block->lifted_at->format('M d, H:i') }}</span>
                                            @elseif($block->ends_at)
                                                <span>{{ $block->ends_at->format('M d, Y H:i') }}</span>
                                            @else
                                                <span>Indefinite</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Manual Block Confirmation Modal -->
    <div id="manual-block-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-xs hidden">
        <div class="bg-white rounded-xl border border-gray-200 shadow-xl max-w-md w-full mx-4 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-2">Manually Block Client</h3>
            <p class="text-xs text-gray-500 mb-4">Restrict {{ $customer->name }} ({{ $customer->phone }}) from booking new appointments.</p>

            <form action="{{ route('company.customers.block', $customer->id) }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="block_reason" class="block mb-1 text-xs font-bold text-gray-700 uppercase tracking-wider">Reason <span class="text-red-500">*</span></label>
                    <input type="text" id="block_reason" name="reason" required placeholder="e.g. Repeated late cancellations, abusive conduct"
                           class="block w-full p-2.5 text-xs text-gray-900 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="mb-6">
                    <label for="duration_days" class="block mb-1 text-xs font-bold text-gray-700 uppercase tracking-wider">Duration (Days)</label>
                    <input type="number" id="duration_days" name="duration_days" min="1" max="365" value="{{ $company->block_duration_days }}"
                           class="block w-full p-2.5 text-xs text-gray-900 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-[11px] text-gray-400 mt-1">Default salon duration is {{ $company->block_duration_days }} days.</p>
                </div>

                <div class="flex items-center justify-end space-x-2">
                    <button type="button" onclick="document.getElementById('manual-block-modal').classList.add('hidden')"
                            class="px-4 py-2 text-xs font-bold text-gray-700 hover:bg-gray-100 rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-lg shadow-sm transition">
                        Confirm Block
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.company>
