<x-layouts.guest>
    <x-slot:title>Manager Password Recovery - Barbar SaaS</x-slot:title>
    <section class="mx-auto my-10 max-w-md rounded-xl border border-gray-200 bg-white p-6">
        <h1 class="text-2xl font-bold">Recover manager password</h1>
        <p class="mt-2 text-sm text-gray-600">Enter your manager email. Employees should ask their salon manager for a password reset.</p>
        @if($errors->any())<div role="alert" class="my-4 text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ route('manager.password.email') }}" class="mt-5 space-y-4">
            @csrf
            <label for="email" class="block text-sm font-medium">Manager email</label>
            <input id="email" name="email" type="email" required maxlength="255" autocomplete="email" value="{{ old('email') }}" class="w-full rounded-lg border border-gray-300 p-3">
            <button class="rounded-lg bg-blue-600 px-5 py-3 font-medium text-white">Request reset link</button>
        </form>
        <a href="{{ route('login') }}" class="mt-4 inline-block text-sm text-blue-700 underline">Back to login</a>
    </section>
</x-layouts.guest>
