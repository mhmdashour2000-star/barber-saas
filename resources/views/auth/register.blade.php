<x-layouts.guest>
    <x-slot:title>Register Salon - Barbar SaaS</x-slot:title>

    <div class="py-12 sm:py-16">
        <div class="max-w-xl mx-auto px-4 sm:px-6">
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200">
                <div class="text-center mb-8">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 mb-3">
                        30-Day Free Trial
                    </span>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Register Your Barber Salon</h1>
                    <p class="text-sm text-gray-500 mt-1">Get started with organized appointment management</p>
                </div>

                @if ($errors->any())
                    <div class="p-4 mb-6 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200" role="alert">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('register') }}" class="space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="manager_name" class="block mb-2 text-sm font-medium text-gray-900">Manager Full Name</label>
                            <input type="text" name="manager_name" id="manager_name" value="{{ old('manager_name') }}" required autofocus
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                placeholder="e.g. Ahmet Yılmaz">
                        </div>

                        <div>
                            <label for="company_name" class="block mb-2 text-sm font-medium text-gray-900">Salon / Company Name</label>
                            <input type="text" name="company_name" id="company_name" value="{{ old('company_name') }}" required
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                placeholder="e.g. Grand Barber Studio">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="email" class="block mb-2 text-sm font-medium text-gray-900">Email Address</label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                placeholder="manager@barbersalon.com">
                        </div>

                        <div>
                            <label for="phone" class="block mb-2 text-sm font-medium text-gray-900">Phone Number</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}" required
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                placeholder="+90 532 000 0000">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="password" class="block mb-2 text-sm font-medium text-gray-900">Password</label>
                            <input type="password" name="password" id="password" required
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                placeholder="••••••••">
                        </div>

                        <div>
                            <label for="password_confirmation" class="block mb-2 text-sm font-medium text-gray-900">Confirm Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" required
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                placeholder="••••••••">
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-3 text-center transition shadow-sm">
                        Create Salon Account & Start Free Trial
                    </button>
                </form>

                <div class="mt-6 text-center text-sm text-gray-600 border-t border-gray-100 pt-6">
                    Already registered?
                    <a href="{{ route('login') }}" class="font-semibold text-blue-600 hover:underline">
                        Log in here
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.guest>
