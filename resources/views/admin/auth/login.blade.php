<x-layouts.guest>
    <x-slot:title>System Admin Login - Barbar SaaS</x-slot:title>

    <div class="py-12 sm:py-16">
        <div class="max-w-md mx-auto px-4 sm:px-6">
            <div class="bg-white p-8 rounded-2xl shadow-md border border-gray-200">
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-slate-900 text-white font-extrabold text-lg mb-3 shadow">
                        B
                    </div>
                    <div class="inline-block px-2.5 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800 mb-2">
                        Restricted Access
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">System Admin Portal</h1>
                    <p class="text-sm text-gray-500 mt-1">Sign in to manage the SaaS platform</p>
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

                <form method="POST" action="{{ route('admin.login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block mb-2 text-sm font-medium text-gray-900">Administrator Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email', 'admin@barbar.local') }}" required autofocus
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-slate-900 focus:border-slate-900 block w-full p-2.5"
                            placeholder="admin@barbar.local">
                    </div>

                    <div>
                        <label for="password" class="block mb-2 text-sm font-medium text-gray-900">Password</label>
                        <input type="password" name="password" id="password" required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-slate-900 focus:border-slate-900 block w-full p-2.5"
                            placeholder="••••••••">
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember" name="remember" type="checkbox"
                                class="w-4 h-4 text-slate-900 bg-gray-100 border-gray-300 rounded focus:ring-slate-900">
                            <label for="remember" class="ml-2 text-sm font-medium text-gray-700">Remember session</label>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full text-white bg-slate-900 hover:bg-slate-800 focus:ring-4 focus:outline-none focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center transition shadow">
                        Log In to Control Panel
                    </button>
                </form>

                <div class="mt-6 text-center text-xs text-gray-500 border-t border-gray-100 pt-6">
                    <a href="{{ route('home') }}" class="hover:text-gray-900 transition">
                        &larr; Back to Public Website
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.guest>
