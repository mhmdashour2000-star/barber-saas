<x-layouts.company>
    <x-slot:title>Confirm Availability Change - Barbar SaaS</x-slot:title>
    <h1 class="text-2xl font-bold">Existing appointments are affected</h1>
    <p class="my-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-900">This availability change has not been saved. The confirmed appointments below would fall outside working hours. Confirming preserves every appointment; you must handle them separately. No customer notifications or violations will be created.</p>
    <div class="space-y-3">
        @foreach($appointments as $appointment)
            <article class="rounded-lg border border-gray-200 bg-white p-4">
                <a href="{{ route('company.appointments.show', $appointment) }}" class="font-semibold text-blue-700 underline">{{ $appointment->booking_code }}</a>
                <p class="break-words">{{ $appointment->customer_name_snapshot }} · {{ $appointment->service_name_snapshot }}</p>
                <p class="text-sm text-gray-600">{{ $appointment->employee?->name ?? 'Unavailable' }} · {{ $appointment->starts_at->copy()->setTimezone(\App\Services\AvailabilityService::TIMEZONE)->format('d M Y H:i') }} (Europe/Istanbul)</p>
            </article>
        @endforeach
    </div>
    <form method="POST" action="{{ $action }}" class="mt-6 flex flex-wrap gap-3">
        @csrf
        @method($method)
        @foreach($fields as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
        <input type="hidden" name="change_confirmation" value="{{ $confirmation }}">
        <button class="rounded-lg bg-amber-700 px-5 py-3 font-semibold text-white hover:bg-amber-800">Confirm change and preserve appointments</button>
        <a href="{{ route('company.availability.index') }}" class="rounded-lg border border-gray-300 bg-white px-5 py-3 font-medium">Keep current availability</a>
    </form>
</x-layouts.company>
