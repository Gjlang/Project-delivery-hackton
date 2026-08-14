<x-dashboard-layout title="Dashboard">
    <div class="p-8 max-w-5xl">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500">Welcome back, {{ auth()->user()->name }}.</p>

        <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-4">
            @php
                $documentCount = \App\Models\Document::count();
                $indexedCount = \App\Models\Document::where('status', 'indexed')->count();
            @endphp
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <p class="text-sm text-gray-500">Documents in Knowledge Base</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $documentCount }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <p class="text-sm text-gray-500">Indexed</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $indexedCount }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <p class="text-sm text-gray-500">Categories</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ count(config('document_categories.categories')) }}</p>
            </div>
        </div>

        <a href="{{ route('company-knowledge.index') }}"
           class="mt-8 inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-700">
            Go to Company Knowledge
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        </a>
    </div>
</x-dashboard-layout>
