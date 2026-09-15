<x-layouts.employee>
    <x-slot:title>My Appointments - Barbar SaaS</x-slot:title>
    <h1 class="text-2xl font-bold">My appointments today</h1>
    <p class="mt-1 text-sm text-gray-600">{{ $today->locale(app()->getLocale())->translatedFormat('l j F Y') }} · Europe/Istanbul</p>
    <div class="my-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach([
            'Today' => $appointments->count(),
            'Completed' => $appointments->where('status', \App\Enums\AppointmentStatus::COMPLETED)->count(),
            'Remaining' => $appointments->where('status', \App\Enums\AppointmentStatus::CONFIRMED)->count(),
            'Cancelled / no-show' => $appointments->filter(fn ($a) => in_array($a->status, [\App\Enums\AppointmentStatus::CANCELLED_BY_CUSTOMER, \App\Enums\AppointmentStatus::CANCELLED_BY_COMPANY, \App\Enums\AppointmentStatus::NO_SHOW], true))->count(),
        ] as $label => $count)
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-sm text-gray-600">{{ $label }}</p><p class="mt-1 text-2xl font-bold">{{ $count }}</p>
            </div>
        @endforeach
    </div>
    <div class="space-y-4">
        @forelse($appointments as $appointment)
            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xl font-bold">{{ $appointment->starts_at->copy()->timezone('Europe/Istanbul')->format('H:i') }}</p>
                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $appointment->status->badgeClass() }}">{{ $appointment->status->label() }}</span>
                </div>
                <h2 class="mt-3 break-words text-lg font-semibold">{{ $appointment->customer_name_snapshot }}</h2>
                <p class="break-words text-gray-600">{{ $appointment->service_name_snapshot }}</p>
                <p class="mt-1 text-xs text-gray-500">Booking {{ $appointment->booking_code }}</p>
                @if($appointment->status === \App\Enums\AppointmentStatus::CONFIRMED && $appointment->starts_at->lte(now()))
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <form method="POST" action="{{ route('employee.appointments.complete', $appointment->booking_code) }}" onsubmit="return confirm('Mark this appointment completed?');">
                            @csrf
                            <button class="min-h-12 w-full rounded-lg bg-blue-600 px-4 py-3 font-medium text-white hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">Mark Completed</button>
                        </form>
                        <form method="POST" action="{{ route('employee.appointments.no-show', $appointment->booking_code) }}" onsubmit="return confirm('Mark this customer as a no-show? This records a customer violation.');">
                            @csrf
                            <button class="min-h-12 w-full rounded-lg border border-red-300 bg-red-50 px-4 py-3 font-medium text-red-800 hover:bg-red-100 focus:ring-4 focus:ring-red-200">Mark No-show</button>
                        </form>
                    </div>
                @elseif($appointment->status === \App\Enums\AppointmentStatus::CONFIRMED)
                    <p class="mt-4 text-sm text-gray-500">Actions become available at the appointment start time.</p>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-600">No appointments assigned to you today.</div>
        @endforelse
    </div>
</x-layouts.employee>
