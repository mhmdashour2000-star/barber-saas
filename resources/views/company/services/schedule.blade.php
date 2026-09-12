<x-layouts.company>
    <x-slot:title>Weekly Schedule: {{ $service->name }} - Barbar SaaS</x-slot:title>

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('company.services.index') }}" class="hover:text-blue-600 transition">Services</a>
                <span>/</span>
                <span class="font-semibold text-gray-800">{{ $service->name }}</span>
                <span>/</span>
                <span>Weekly Schedule</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Weekly Service Availability</h1>
            <p class="text-sm text-gray-500 mt-1">Configure the days and time windows during which "{{ $service->name }}" can be booked.</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('company.services.index') }}" class="inline-flex items-center px-3 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-xs">
                &larr; Back to Services
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

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden max-w-4xl">
        <form action="{{ route('company.services.schedule.update', $service->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="p-6 sm:p-8 divide-y divide-gray-200">
                @php
                    $daysOfWeek = [
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday',
                        7 => 'Sunday',
                    ];
                @endphp

                @foreach($daysOfWeek as $dayNumber => $dayName)
                    @php
                        $dayWindows = $scheduleByDay[$dayNumber] ?? collect();
                    @endphp
                    <div class="py-5 first:pt-0 last:pb-0" data-day="{{ $dayNumber }}">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                            <!-- Day Title & Toggle -->
                            <div class="w-full sm:w-48">
                                <span class="text-sm font-bold text-gray-900 block">{{ $dayName }}</span>
                                <span class="text-xs text-gray-500 block mt-0.5">
                                    {{ $dayWindows->isEmpty() ? 'Unavailable / Closed' : $dayWindows->count() . ' window(s)' }}
                                </span>
                            </div>

                            <!-- Windows Container -->
                            <div class="flex-1">
                                <div class="space-y-2.5 windows-list" id="windows-container-{{ $dayNumber }}">
                                    @if($dayWindows->isEmpty())
                                        <p class="text-xs text-gray-400 italic py-1 no-windows-msg">No operational hours set for this day.</p>
                                    @else
                                        @foreach($dayWindows as $idx => $win)
                                            <div class="flex items-center space-x-2 window-row">
                                                <input type="time" name="days[{{ $dayNumber }}][{{ $idx }}][start_time]"
                                                       value="{{ substr($win->start_time, 0, 5) }}" required
                                                       class="text-xs p-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                                <span class="text-xs text-gray-400">to</span>
                                                <input type="time" name="days[{{ $dayNumber }}][{{ $idx }}][end_time]"
                                                       value="{{ substr($win->end_time, 0, 5) }}" required
                                                       class="text-xs p-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                                <button type="button" onclick="this.closest('.window-row').remove()"
                                                        class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg transition" title="Remove window">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>

                                <div class="mt-2">
                                    <button type="button" onclick="addWindow({{ $dayNumber }})"
                                            class="inline-flex items-center text-xs font-semibold text-blue-600 hover:text-blue-800 transition">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        + Add Window
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Footer Save Actions -->
            <div class="p-6 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                <a href="{{ route('company.services.index') }}" class="text-xs font-bold text-gray-600 uppercase tracking-wider hover:text-gray-900">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white uppercase tracking-wider bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm focus:ring-4 focus:ring-blue-300 transition">
                    Save Weekly Schedule
                </button>
            </div>
        </form>
    </div>

    <script>
        function addWindow(day) {
            const container = document.getElementById('windows-container-' + day);
            const noMsg = container.querySelector('.no-windows-msg');
            if (noMsg) noMsg.remove();

            const idx = container.querySelectorAll('.window-row').length + Date.now();
            const row = document.createElement('div');
            row.className = 'flex items-center space-x-2 window-row';
            row.innerHTML = `
                <input type="time" name="days[${day}][${idx}][start_time]" value="09:00" required
                       class="text-xs p-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <span class="text-xs text-gray-400">to</span>
                <input type="time" name="days[${day}][${idx}][end_time]" value="18:00" required
                       class="text-xs p-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <button type="button" onclick="this.closest('.window-row').remove()"
                        class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg transition" title="Remove window">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            `;
            container.appendChild(row);
        }
    </script>
</x-layouts.company>
