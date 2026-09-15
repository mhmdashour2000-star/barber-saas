<x-layouts.guest>
    <x-slot:title>Reset Manager Password - Barbar SaaS</x-slot:title>
    <section class="mx-auto my-10 max-w-md rounded-xl border border-gray-200 bg-white p-6">
        <h1 class="text-2xl font-bold">Reset manager password</h1>
        @if($errors->any())<div role="alert" class="my-4 text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ route('manager.password.update') }}" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="email" class="block text-sm font-medium">Manager email</label>
            <input id="email" type="email" name="email" value="{{ $email }}" required maxlength="255" autocomplete="email" class="w-full rounded-lg border border-gray-300 p-3">
            @foreach(['password' => 'New password', 'password_confirmation' => 'Confirm new password'] as $field => $label)
                <div><label for="{{ $field }}" class="mb-2 block text-sm font-medium">{{ $label }}</label><input id="{{ $field }}" type="password" name="{{ $field }}" required minlength="8" maxlength="255" autocomplete="new-password" class="w-full rounded-lg border border-gray-300 p-3"></div>
            @endforeach
            <button class="rounded-lg bg-blue-600 px-5 py-3 font-medium text-white">Reset password</button>
        </form>
    </section>
</x-layouts.guest>
