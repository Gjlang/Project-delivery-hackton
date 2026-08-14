<x-dashboard-layout title="Dashboard">
    <div class="p-8 max-w-5xl">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                <p class="mt-1 text-sm text-gray-500">Welcome back, {{ auth()->user()->name }}.</p>
            </div>
            <a href="{{ route('projects.new') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                + New Project
            </a>
        </div>

        <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <p class="text-sm text-gray-500">Projects</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $projectCount }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <p class="text-sm text-gray-500">Documents Uploaded</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $documentCount }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <p class="text-sm text-gray-500">Documents Indexed</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $indexedCount }}</p>
            </div>
        </div>

        <div class="mt-8 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">Recent Projects</h2>
            <a href="{{ route('projects.index') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700">
                View All &rarr;
            </a>
        </div>

        <div class="mt-3 bg-white border border-gray-200 rounded-xl overflow-hidden">
            @forelse ($projects as $project)
                <a href="{{ route('projects.company-knowledge.index', $project) }}"
                   class="flex items-center justify-between px-5 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $project->name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $project->documents_count }} document{{ $project->documents_count === 1 ? '' : 's' }} &middot; {{ ucfirst($project->status) }}</p>
                    </div>
                    <span class="text-xs font-medium text-blue-600">Continue &rarr;</span>
                </a>
            @empty
                <div class="px-5 py-10 text-center text-sm text-gray-400">
                    No projects yet.
                    <a href="{{ route('projects.new') }}" class="block mt-2 text-blue-600 font-medium hover:underline">
                        Create your first project &rarr;
                    </a>
                </div>
            @endforelse
        </div>
    </div>
</x-dashboard-layout>
