<x-layouts.employee>
    <x-slot:title>Change Password - Barbar SaaS</x-slot:title>
    <section class="mx-auto max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold">Choose your own password</h1>
        <p class="mt-2 text-sm text-gray-600">Replace your temporary password before accessing your appointments.</p>
        <form method="POST" action="{{ route('employee.password.update') }}" class="mt-6 space-y-5">
            @csrf
            @method('PUT')
            @foreach(['current_password' => 'Current password', 'password' => 'New password', 'password_confirmation' => 'Confirm new password'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="password" required maxlength="255" autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" class="block w-full rounded-lg border border-gray-300 p-3 focus:border-blue-500 focus:ring-blue-500">
                </div>
            @endforeach
            <p class="text-sm text-gray-600">Use at least eight characters and a different password from your temporary one.</p>
            <button class="min-h-12 w-full rounded-lg bg-blue-600 px-5 py-3 font-medium text-white hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">Save password</button>
        </form>
    </section>
</x-layouts.employee>
