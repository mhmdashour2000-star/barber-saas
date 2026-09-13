<x-layouts.company>
    <x-slot:title>Appointments - Barbar SaaS</x-slot:title>

    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Appointments</h1>
        <p class="mt-1 text-sm text-gray-500">Manage your salon's bookings. All times are shown in Istanbul time.</p>
    </div>

    @if($company->status === \App\Models\Company::STATUS_SUSPENDED)
        <div role="status" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Your salon is suspended. Appointment history is available in read-only mode.
        </div>
    @endif

    <form method="GET" action="{{ route('company.appointments.index') }}" class="mb-6 flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="appointment-search" class="mb-1 block text-sm font-medium text-gray-700">Search appointments</label>
            <input id="appointment-search" type="search" name="search" maxlength="100" value="{{ $search }}" placeholder="Booking code, customer name or phone"
                   class="w-full rounded-lg border border-gray-300 p-2.5 text-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label for="appointment-filter" class="mb-1 block text-sm font-medium text-gray-700">Show</label>
            <select id="appointment-filter" name="filter" class="w-full rounded-lg border border-gray-300 p-2.5 text-sm sm:w-48">
                @foreach(['all' => 'All appointments', 'today' => 'Today', 'upcoming' => 'Upcoming', 'completed' => 'Completed', 'no-show' => 'No-show', 'cancelled' => 'Cancelled'] as $value => $label)
                    <option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Apply filters</button>
        <a href="{{ route('company.appointments.index') }}" class="rounded-lg bg-gray-100 px-4 py-2.5 text-center text-sm text-gray-700 hover:bg-gray-200">Clear</a>
    </form>

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <caption class="sr-only">Your salon's appointments, in Istanbul time</caption>
                <thead class="bg-gray-50 text-xs uppercase text-gray-700">
                    <tr>
                        @foreach(['Booking code', 'Customer', 'Phone', 'Service', 'Employee', 'Start', 'End', 'Status'] as $heading)
                            <th scope="col" class="whitespace-nowrap px-5 py-3">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($appointments as $appointment)
                        <tr class="hover:bg-gray-50">
                            <th scope="row" class="whitespace-nowrap px-5 py-4 font-mono">
                                <a href="{{ route('company.appointments.show', $appointment) }}" class="font-semibold text-blue-700 underline-offset-4 hover:underline">{{ $appointment->booking_code }}</a>
                            </th>
                            <td class="px-5 py-4 font-medium text-gray-900">{{ $appointment->customer_name_snapshot }}</td>
                            <td class="whitespace-nowrap px-5 py-4">{{ $appointment->customer_phone_snapshot }}</td>
                            <td class="px-5 py-4">{{ $appointment->service_name_snapshot }}</td>
                            <td class="px-5 py-4">{{ $appointment->employee?->name ?? $appointment->employee_name_snapshot }}</td>
                            <td class="whitespace-nowrap px-5 py-4">{{ $appointment->starts_at->copy()->setTimezone($timezone)->format('d M Y H:i') }}</td>
                            <td class="whitespace-nowrap px-5 py-4">{{ $appointment->ends_at->copy()->setTimezone($timezone)->format('d M Y H:i') }}</td>
                            <td class="whitespace-nowrap px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $appointment->status->badgeClass() }}">{{ $appointment->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-14 text-center text-gray-500">No appointments match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($appointments->hasPages())
            <div class="border-t border-gray-200 p-4">{{ $appointments->links() }}</div>
        @endif
    </div>
</x-layouts.company>
