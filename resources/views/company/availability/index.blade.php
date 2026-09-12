<x-layouts.company>
    <x-slot:title>Salon Availability & Date Exceptions - Barbar SaaS</x-slot:title>

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Availability &amp; Exceptions</h1>
            <p class="text-sm text-gray-500 mt-1">Manage salon closures, public holidays, staff leaves, and special date-specific operating hours.</p>
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

    @if($errors->any())
        <div class="p-4 mb-6 text-sm text-red-800 rounded-xl bg-red-50 border border-red-200">
            <span class="font-bold block mb-1">Please correct the following errors:</span>
            <ul class="list-disc pl-5 space-y-1 text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left: Create Exception Form -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4">Add Date Exception</h2>

                @if($company->status === \App\Models\Company::STATUS_SUSPENDED)
                    <div class="p-4 text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg">
                        Account suspended. Adding new exceptions is disabled.
                    </div>
                @else
                    <form action="{{ route('company.availability.exceptions.store') }}" method="POST">
                        @csrf

                        <!-- Scope Type Selection -->
                        <div class="mb-4">
                            <label for="type" class="block mb-1.5 text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Exception Scope <span class="text-red-500">*</span>
                            </label>
                            <select id="type" name="type" required onchange="handleTypeChange(this.value)"
                                    class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <option value="company" {{ old('type') === 'company' ? 'selected' : '' }}>Full Salon Closure / Special Hours</option>
                                <option value="service" {{ old('type') === 'service' ? 'selected' : '' }}>Specific Service Exception</option>
                                <option value="employee" {{ old('type') === 'employee' ? 'selected' : '' }}>Staff Member Leave / Shift Change</option>
                            </select>
                        </div>

                        <!-- Service Target (conditional) -->
                        <div id="service-select-group" class="mb-4 {{ old('type') === 'service' ? '' : 'hidden' }}">
                            <label for="service_id" class="block mb-1.5 text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Select Service <span class="text-red-500">*</span>
                            </label>
                            <select id="service_id" name="service_id"
                                    class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <option value="">-- Choose Service --</option>
                                @foreach($services as $s)
                                    <option value="{{ $s->id }}" {{ old('service_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Employee Target (conditional) -->
                        <div id="employee-select-group" class="mb-4 {{ old('type') === 'employee' ? '' : 'hidden' }}">
                            <label for="employee_id" class="block mb-1.5 text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Select Staff Member <span class="text-red-500">*</span>
                            </label>
                            <select id="employee_id" name="employee_id"
                                    class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <option value="">-- Choose Staff --</option>
                                @foreach($employees as $e)
                                    <option value="{{ $e->id }}" {{ old('employee_id') == $e->id ? 'selected' : '' }}>{{ $e->name }} ({{ '@' . $e->username }})</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Date -->
                        <div class="mb-4">
                            <label for="date" class="block mb-1.5 text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="date" name="date" min="{{ date('Y-m-d') }}" value="{{ old('date', date('Y-m-d')) }}" required
                                   class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Full Day Closed Toggle -->
                        <div class="mb-4">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="is_closed" name="is_closed" value="1"
                                       {{ old('is_closed', '1') == '1' ? 'checked' : '' }}
                                       onchange="handleClosedToggle(this.checked)" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                                <span class="ml-3 text-xs font-bold text-gray-700 uppercase tracking-wider">Fully Closed / Off Duty</span>
                            </label>
                            <p class="text-[11px] text-gray-500 mt-1">Uncheck to set custom special hours instead of closing completely.</p>
                        </div>

                        <!-- Custom Hours Windows (Conditional when NOT closed) -->
                        <div id="custom-windows-group" class="mb-4 {{ old('is_closed', '1') == '1' ? 'hidden' : '' }}">
                            <label class="block mb-1.5 text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Special Operating Hours
                            </label>
                            <div id="exception-windows-container" class="space-y-2">
                                <div class="flex items-center space-x-2 exc-window-row">
                                    <input type="time" name="windows[0][start_time]" value="10:00"
                                           class="text-xs p-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    <span class="text-xs text-gray-400">to</span>
                                    <input type="time" name="windows[0][end_time]" value="14:00"
                                           class="text-xs p-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>

                        <!-- Reason / Description -->
                        <div class="mb-6">
                            <label for="reason" class="block mb-1.5 text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Reason / Note <span class="text-gray-400 font-normal lowercase">(optional)</span>
                            </label>
                            <input type="text" id="reason" name="reason" value="{{ old('reason') }}"
                                   placeholder="e.g. National Holiday, Staff Annual Leave, Renovations"
                                   class="block w-full p-2.5 text-sm text-gray-900 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <button type="submit" class="w-full px-4 py-2.5 text-xs font-bold text-white uppercase tracking-wider bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm focus:ring-4 focus:ring-blue-300 transition">
                            Save Exception
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Right: Exceptions List Table -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-base font-bold text-gray-900">Upcoming &amp; Active Exceptions</h2>
                    <span class="text-xs text-gray-500">{{ $exceptions->total() }} Total Exceptions</span>
                </div>

                @if($exceptions->isEmpty())
                    <div class="p-8 text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 text-gray-500 mb-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900">No Date Exceptions</h3>
                        <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                            Your salon is operating under standard weekly schedules. Add holiday closures or special working hours using the form on the left.
                        </p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th scope="col" class="px-5 py-3 font-bold">Date</th>
                                    <th scope="col" class="px-5 py-3 font-bold">Scope &amp; Target</th>
                                    <th scope="col" class="px-5 py-3 font-bold">Status / Hours</th>
                                    <th scope="col" class="px-5 py-3 font-bold">Reason</th>
                                    <th scope="col" class="px-5 py-3 font-bold text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($exceptions as $exc)
                                    <tr class="hover:bg-gray-50 transition">
                                        <!-- Date -->
                                        <td class="px-5 py-3.5 whitespace-nowrap">
                                            <div class="font-bold text-gray-900 text-xs">{{ $exc->date->format('M d, Y') }}</div>
                                            <div class="text-[11px] text-gray-400">{{ $exc->date->format('l') }}</div>
                                        </td>

                                        <!-- Scope & Target -->
                                        <td class="px-5 py-3.5 whitespace-nowrap">
                                            @if($exc->type === 'company')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-800">
                                                    Entire Salon
                                                </span>
                                            @elseif($exc->type === 'service')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-blue-100 text-blue-800">
                                                    Service: {{ $exc->service?->name ?? 'Deleted' }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-purple-100 text-purple-800">
                                                    Staff: {{ $exc->employee?->name ?? 'Deleted' }}
                                                </span>
                                            @endif
                                        </td>

                                        <!-- Status / Hours -->
                                        <td class="px-5 py-3.5">
                                            @if($exc->is_closed)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-800">
                                                    Fully Closed
                                                </span>
                                            @else
                                                <div class="text-xs text-gray-700">
                                                    @foreach($exc->windows as $win)
                                                        <span class="font-mono">{{ substr($win->start_time, 0, 5) }}–{{ substr($win->end_time, 0, 5) }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>

                                        <!-- Reason -->
                                        <td class="px-5 py-3.5 text-xs text-gray-600">
                                            {{ $exc->reason ?: '—' }}
                                        </td>

                                        <!-- Delete Action -->
                                        <td class="px-5 py-3.5 whitespace-nowrap text-right text-xs">
                                            @if($company->status !== 'suspended')
                                                <form action="{{ route('company.availability.exceptions.destroy', $exc->id) }}" method="POST" class="inline"
                                                      onsubmit="return confirm('Are you sure you want to remove this date exception?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-800 font-semibold transition">
                                                        Remove
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-gray-400">Locked</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($exceptions->hasPages())
                        <div class="p-4 border-t border-gray-200 bg-gray-50">
                            {{ $exceptions->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <script>
        function handleTypeChange(val) {
            document.getElementById('service-select-group').classList.toggle('hidden', val !== 'service');
            document.getElementById('employee-select-group').classList.toggle('hidden', val !== 'employee');
        }

        function handleClosedToggle(isClosed) {
            document.getElementById('custom-windows-group').classList.toggle('hidden', isClosed);
        }
    </script>
</x-layouts.company>
