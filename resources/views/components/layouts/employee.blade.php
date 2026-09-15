<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Employee - Barbar SaaS' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 px-4 py-4">
            <div class="min-w-0 break-words">
                <p class="font-bold">{{ auth('employee')->user()?->company?->name ?? 'Barbar SaaS' }}</p>
                <p class="text-sm text-gray-600">{{ auth('employee')->user()?->name ?? 'Employee portal' }}</p>
            </div>
            @auth('employee')
                <form method="POST" action="{{ route('employee.logout') }}">
                    @csrf
                    <button class="min-h-11 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-100 focus:ring-4 focus:ring-blue-200">Log out</button>
                </form>
            @endauth
        </div>
    </header>
    <main class="mx-auto max-w-3xl px-4 py-6 sm:py-8">
        @if(session('status'))
            <div role="status" class="mb-4 rounded-lg bg-green-50 p-4 text-green-800">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        {{ $slot }}
    </main>
</body>
</html>
