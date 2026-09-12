<x-layouts.guest>
    <x-slot:title>Log In - Barbar SaaS</x-slot:title>

    <div class="py-12 sm:py-16">
        <div class="max-w-md mx-auto px-4 sm:px-6">
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200">
                <div class="text-center mb-8">
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Log in to your account</h1>
                    <p class="text-sm text-gray-500 mt-1">Access your salon management dashboard</p>
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

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block mb-2 text-sm font-medium text-gray-900">Email Address</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                            placeholder="manager@barbersalon.com">
                    </div>

                    <div>
                        <label for="password" class="block mb-2 text-sm font-medium text-gray-900">Password</label>
                        <input type="password" name="password" id="password" required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                            placeholder="••••••••">
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember" name="remember" type="checkbox"
                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                            <label for="remember" class="ml-2 text-sm font-medium text-gray-700">Remember me</label>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center transition">
                        Log In
                    </button>
                </form>

                <div class="mt-6 text-center text-sm text-gray-600 border-t border-gray-100 pt-6">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="font-semibold text-blue-600 hover:underline">
                        Register your salon
                    </a>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="{{ route('admin.login') }}" class="text-xs text-gray-400 hover:text-gray-600 transition">
                    System Admin Portal &rarr;
                </a>
            </div>
        </div>
    </div>
</x-layouts.guest>
