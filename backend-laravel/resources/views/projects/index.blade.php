<x-dashboard-layout title="Projects">
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Projects</h1>
                <p class="mt-1 text-sm text-gray-500">Each project has its own documents, AI analysis, and plan.</p>
            </div>
            <a href="#"
               class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                + New Project
            </a>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <div class="mt-6 bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                        <th class="px-5 py-3 font-medium">Project</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Documents</th>
                        <th class="px-5 py-3 font-medium">Created</th>
                        <th class="px-5 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projects as $project)
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <p class="text-gray-900 font-medium">{{ $project->name }}</p>
                                @if ($project->description)
                                    <p class="text-xs text-gray-400 mt-0.5">{{ \Illuminate\Support\Str::limit($project->description, 80) }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ ucfirst($project->status) }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $project->documents_count }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $project->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('projects.company-knowledge.index', $project) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                        Project Documents
                                    </a>
                                    <a href="{{ route('projects.ai-analysis.index', $project) }}" class="text-gray-500 hover:text-gray-700 text-xs font-medium">
                                        AI Analysis
                                    </a>
                                    <a href="{{ route('projects.testing.create', $project) }}" class="text-gray-500 hover:text-gray-700 text-xs font-medium">
                                        Testing
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-400">
                                No projects yet.
                                <a href="#" class="block mt-2 text-blue-600 font-medium hover:underline">
                                    Create your first project &rarr;
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-dashboard-layout>
