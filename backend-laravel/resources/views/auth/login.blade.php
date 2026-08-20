<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sign In &middot; ProjectFlow AI</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="relative min-h-screen flex items-center justify-center overflow-hidden bg-gray-50 px-4">
        {{-- Soft decorative gradient blobs -- purely visual, no interaction --}}
        <div class="pointer-events-none absolute -top-32 -left-24 h-96 w-96 rounded-full bg-blue-200/50 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -right-24 h-96 w-96 rounded-full bg-indigo-200/40 blur-3xl"></div>
        <div class="pointer-events-none absolute top-1/2 left-1/2 h-[32rem] w-[32rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-blue-50/60 blur-3xl"></div>

        <div class="relative w-full max-w-sm">
            {{-- Brand mark --}}
            <div class="flex flex-col items-center text-center">
                <div class="h-12 w-12 rounded-2xl bg-blue-600 shadow-lg shadow-blue-600/20 flex items-center justify-center">
                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M18.4 5.6l-2.8 2.8M8.4 15.6l-2.8 2.8" />
                    </svg>
                </div>
                <p class="mt-4 text-lg font-bold tracking-tight text-gray-900">ProjectFlow AI</p>
                <p class="text-xs text-gray-400">Enterprise Planner</p>
            </div>

            {{-- Card --}}
            <div class="mt-8 bg-white rounded-2xl shadow-xl shadow-gray-200/60 border border-gray-100 px-8 py-9">
                <div class="text-center">
                    <h1 class="text-xl font-semibold text-gray-900">Welcome back</h1>
                    <p class="mt-1.5 text-sm text-gray-500">
                        AI-assisted project planning, rule validation, and delivery tracking in one place.
                    </p>
                </div>

                <x-auth-session-status class="mt-5 text-center text-sm text-green-600" :status="session('status')" />

                <a href="{{ route('auth.google.redirect') }}"
                   class="mt-7 group flex w-full items-center justify-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-sm transition hover:border-gray-300 hover:bg-gray-50 hover:shadow-md">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M23.52 12.27c0-.82-.07-1.6-.2-2.36H12v4.47h6.47c-.28 1.5-1.13 2.78-2.4 3.63v3.02h3.88c2.27-2.09 3.57-5.17 3.57-8.76Z"/>
                        <path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.95-2.9l-3.88-3.02c-1.08.72-2.45 1.15-4.07 1.15-3.13 0-5.78-2.11-6.73-4.96H1.27v3.12A12 12 0 0 0 12 24Z"/>
                        <path fill="#FBBC05" d="M5.27 14.27a7.2 7.2 0 0 1 0-4.54V6.61H1.27a12 12 0 0 0 0 10.78l4-3.12Z"/>
                        <path fill="#EA4335" d="M12 4.77c1.76 0 3.34.6 4.58 1.79l3.44-3.44C17.95 1.19 15.24 0 12 0A12 12 0 0 0 1.27 6.61l4 3.12C6.22 6.88 8.87 4.77 12 4.77Z"/>
                    </svg>
                    Continue with Google
                    <svg class="h-4 w-4 shrink-0 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <p class="mt-6 text-center text-xs text-gray-400">
                Sign-in is restricted to your organization's Google account.
            </p>
        </div>
    </div>
</body>
</html>
