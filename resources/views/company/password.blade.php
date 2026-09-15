<x-layouts.company>
    <x-slot:title>Change Manager Password - Barbar SaaS</x-slot:title>
    <section class="max-w-lg rounded-xl border border-gray-200 bg-white p-6">
        <h1 class="text-2xl font-bold">Change manager password</h1>
        @if($errors->any())<div role="alert" class="my-4 text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ route('company.password.change') }}" class="mt-5 space-y-4">
            @csrf @method('PUT')
            @foreach(['current_password' => 'Current password', 'password' => 'New password', 'password_confirmation' => 'Confirm new password'] as $field => $label)
                <div><label for="{{ $field }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>
                <input type="password" id="{{ $field }}" name="{{ $field }}" required maxlength="255" autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" class="w-full rounded-lg border border-gray-300 p-3"></div>
            @endforeach
            <p class="text-sm text-gray-500">At least eight characters. Other manager sessions will require login again.</p>
            <button class="rounded-lg bg-blue-600 px-5 py-3 font-medium text-white">Change password</button>
        </form>
    </section>
</x-layouts.company>
