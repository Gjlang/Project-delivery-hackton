<x-dashboard-layout :title="$title" :project="$project ?? null">
    <div class="p-8 max-w-3xl">
        <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
        <p class="mt-2 text-sm text-gray-500">{{ $description }}</p>

        <div class="mt-8 border border-dashed border-gray-300 rounded-xl p-12 text-center text-gray-400 text-sm">
            This section is coming soon.
        </div>
    </div>
</x-dashboard-layout>
