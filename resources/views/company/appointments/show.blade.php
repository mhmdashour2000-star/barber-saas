<x-layouts.company>
    <x-slot:title>Appointment {{ $appointment->booking_code }} - Barbar SaaS</x-slot:title>

    <a href="{{ route('company.appointments.index') }}" class="mb-4 inline-block text-sm font-medium text-blue-700 hover:underline">&larr; All appointments</a>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Appointment <span class="font-mono">{{ $appointment->booking_code }}</span></h1>
            <p class="mt-1 text-sm text-gray-500">All dates and times are shown in Istanbul time.</p>
        </div>
        <span class="rounded-full px-3 py-1.5 text-sm font-semibold {{ $appointment->status->badgeClass() }}">{{ $appointment->status->label() }}</span>
    </div>

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">The appointment could not be changed.</p>
            <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @if($company->status === \App\Models\Company::STATUS_SUSPENDED)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Your salon is suspended. Appointment history is available in read-only mode.</div>
    @endif

    <section aria-labelledby="booking-details" class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 id="booking-details" class="mb-5 text-lg font-bold text-gray-900">Booking details</h2>
        <dl class="grid grid-cols-1 gap-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-gray-500">Customer at booking</dt><dd class="mt-1 font-semibold">{{ $appointment->customer_name_snapshot }}</dd></div>
            <div><dt class="text-gray-500">Phone at booking</dt><dd class="mt-1 font-semibold">{{ $appointment->customer_phone_snapshot }}</dd></div>
            <div><dt class="text-gray-500">Service at booking</dt><dd class="mt-1 font-semibold">{{ $appointment->service_name_snapshot }}</dd></div>
            <div><dt class="text-gray-500">Original employee</dt><dd class="mt-1 font-semibold">{{ $appointment->employee_name_snapshot }}</dd></div>
            <div><dt class="text-gray-500">Current employee</dt><dd class="mt-1 font-semibold">{{ $appointment->employee?->name ?? 'Unavailable' }}</dd></div>
            <div><dt class="text-gray-500">Booked price</dt><dd class="mt-1 font-semibold">{{ $appointment->formatted_snapshot_price }}</dd></div>
            <div><dt class="text-gray-500">Booked duration</dt><dd class="mt-1 font-semibold">{{ $appointment->service_duration_minutes_snapshot }} minutes</dd></div>
            <div><dt class="text-gray-500">Start</dt><dd class="mt-1 font-semibold">{{ $appointment->starts_at->copy()->setTimezone($timezone)->format('d M Y H:i') }}</dd></div>
            <div><dt class="text-gray-500">End</dt><dd class="mt-1 font-semibold">{{ $appointment->ends_at->copy()->setTimezone($timezone)->format('d M Y H:i') }}</dd></div>
            <div><dt class="text-gray-500">Created</dt><dd class="mt-1 font-semibold">{{ $appointment->created_at->copy()->setTimezone($timezone)->format('d M Y H:i') }}</dd></div>
        </dl>
    </section>

    @if($company->status === \App\Models\Company::STATUS_ACTIVE && $appointment->status === \App\Enums\AppointmentStatus::CONFIRMED)
        <section aria-labelledby="reschedule-heading" class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 id="reschedule-heading" class="text-lg font-bold text-gray-900">Reschedule</h2>
            <p class="mt-1 text-sm text-gray-500">Choose a new time in Istanbul. The booked service, price, and duration stay the same.</p>
            <form method="POST" action="{{ route('company.appointments.reschedule', $appointment) }}" class="mt-5 grid grid-cols-1 items-end gap-4 sm:grid-cols-3">
                @csrf
                @method('PATCH')
                <div>
                    <label for="starts_at" class="mb-1 block text-sm font-medium">New start (Istanbul time)</label>
                    <input type="datetime-local" id="starts_at" name="starts_at" required step="60"
                           value="{{ is_string(old('starts_at')) ? old('starts_at') : $appointment->starts_at->copy()->setTimezone($timezone)->format('Y-m-d\TH:i') }}"
                           class="w-full rounded-lg border border-gray-300 p-2.5 text-sm">
                </div>
                <div>
                    <label for="employee_id" class="mb-1 block text-sm font-medium">Employee</label>
                    <select id="employee_id" name="employee_id" class="w-full rounded-lg border border-gray-300 p-2.5 text-sm">
                        <option value="">Keep current employee</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(is_scalar(old('employee_id')) && (string) old('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Save reschedule</button>
            </form>
        </section>

        <section aria-labelledby="actions-heading" class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 id="actions-heading" class="text-lg font-bold text-gray-900">Appointment actions</h2>
            <p class="mt-1 text-sm text-gray-500">These actions are final. A salon cancellation does not add a customer violation.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('company.appointments.cancel', $appointment) }}" onsubmit="return confirm('Cancel this appointment? This action is final.');">
                    @csrf
                    <button type="submit" class="rounded-lg border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">Cancel appointment</button>
                </form>
                @if($appointment->starts_at->lte(now()))
                    <form method="POST" action="{{ route('company.appointments.complete', $appointment) }}" onsubmit="return confirm('Mark this appointment completed? This action is final.');">
                        @csrf
                        <button type="submit" class="rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-800">Mark completed</button>
                    </form>
                    <form method="POST" action="{{ route('company.appointments.no-show', $appointment) }}" onsubmit="return confirm('Mark this appointment as no-show? This adds a customer violation and is final.');">
                        @csrf
                        <button type="submit" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800">Mark no-show</button>
                    </form>
                @else
                    <p class="self-center text-sm text-gray-500">Completion and no-show become available at the appointment start time.</p>
                @endif
            </div>
        </section>
    @endif

    <section aria-labelledby="history-heading" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 id="history-heading" class="mb-5 text-lg font-bold text-gray-900">Event history</h2>
        <ol class="space-y-5">
            @forelse($appointment->events as $event)
                <li class="border-l-2 border-gray-200 pl-4">
                    <p class="font-semibold text-gray-900">{{ $event->type->label() }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $event->created_at->copy()->setTimezone($timezone)->format('d M Y H:i:s') }} · {{ ucfirst($event->actor_type ?? 'system') }}</p>
                    @if($event->type === \App\Enums\AppointmentEventType::RESCHEDULED)
                        <dl class="mt-2 grid gap-2 text-sm text-gray-600 sm:grid-cols-2">
                            @foreach(['old' => 'Previous', 'new' => 'New'] as $prefix => $label)
                                <div>
                                    <dt class="font-medium">{{ $label }} booking</dt>
                                    <dd>
                                        {{ $event->metadata[$prefix.'_employee_name'] ?? 'Unknown employee' }}
                                        @if(isset($event->metadata[$prefix.'_starts_at'], $event->metadata[$prefix.'_ends_at']))
                                            <br>{{ \Carbon\Carbon::parse($event->metadata[$prefix.'_starts_at'])->setTimezone($timezone)->format('d M Y H:i') }}
                                            &ndash; {{ \Carbon\Carbon::parse($event->metadata[$prefix.'_ends_at'])->setTimezone($timezone)->format('d M Y H:i') }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </li>
            @empty
                <li class="text-sm text-gray-500">No events recorded.</li>
            @endforelse
        </ol>
    </section>
</x-layouts.company>
