<x-layouts.employee>
    <x-slot:title>Employee Login - Barbar SaaS</x-slot:title>
    <section class="mx-auto max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-2xl font-bold">Employee login</h1>
        <p class="mt-2 text-sm text-gray-600">Use the company code and login details provided by your salon manager. No email is needed.</p>
        <form method="POST" action="{{ route('employee.login') }}" class="mt-6 space-y-5">
            @csrf
            <div>
                <label for="company_code" class="mb-2 block text-sm font-medium">Company Code</label>
                <input id="company_code" name="company_code" value="{{ old('company_code') }}" placeholder="SLN-JRUUA" required maxlength="50" autocomplete="organization" autocapitalize="characters" spellcheck="false" class="block w-full rounded-lg border border-gray-300 p-3 focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="username" class="mb-2 block text-sm font-medium">Username</label>
                <input id="username" name="username" value="{{ old('username') }}" required maxlength="50" autocomplete="username" autocapitalize="none" spellcheck="false" class="block w-full rounded-lg border border-gray-300 p-3 focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="password" class="mb-2 block text-sm font-medium">Password</label>
                <input id="password" type="password" name="password" required maxlength="255" autocomplete="current-password" class="block w-full rounded-lg border border-gray-300 p-3 focus:border-blue-500 focus:ring-blue-500">
            </div>
            <button class="min-h-12 w-full rounded-lg bg-blue-600 px-5 py-3 font-medium text-white hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">Log in</button>
        </form>
        <p class="mt-5 text-sm text-gray-600">Forgot your password? Ask your salon manager to reset it.</p>
        <a href="{{ route('login') }}" class="mt-4 inline-block py-2 text-sm font-medium text-blue-700 underline">Salon manager login</a>
    </section>
</x-layouts.employee>
