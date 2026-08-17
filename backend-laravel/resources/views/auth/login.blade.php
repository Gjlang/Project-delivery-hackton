<x-guest-layout>
    <div class="text-center">
        <h1 class="text-lg font-semibold text-gray-900">Sign in to ProjectFlow AI</h1>
        <p class="mt-1 text-sm text-gray-500">Use your Google account to continue.</p>

        <x-auth-session-status class="mt-4" :status="session('status')" />

        <a href="{{ route('auth.google.redirect') }}"
           class="mt-6 inline-flex w-full items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
            <svg class="h-5 w-5" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M23.52 12.27c0-.82-.07-1.6-.2-2.36H12v4.47h6.47c-.28 1.5-1.13 2.78-2.4 3.63v3.02h3.88c2.27-2.09 3.57-5.17 3.57-8.76Z"/>
                <path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.95-2.9l-3.88-3.02c-1.08.72-2.45 1.15-4.07 1.15-3.13 0-5.78-2.11-6.73-4.96H1.27v3.12A12 12 0 0 0 12 24Z"/>
                <path fill="#FBBC05" d="M5.27 14.27a7.2 7.2 0 0 1 0-4.54V6.61H1.27a12 12 0 0 0 0 10.78l4-3.12Z"/>
                <path fill="#EA4335" d="M12 4.77c1.76 0 3.34.6 4.58 1.79l3.44-3.44C17.95 1.19 15.24 0 12 0A12 12 0 0 0 1.27 6.61l4 3.12C6.22 6.88 8.87 4.77 12 4.77Z"/>
            </svg>
            Continue with Google
        </a>
    </div>
</x-guest-layout>
