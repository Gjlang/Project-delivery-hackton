<x-dashboard-layout title="Testing">
    <div class="p-8">
        <h1 class="text-2xl font-bold text-gray-900">Website Testing</h1>
        <p class="mt-1 text-sm text-gray-500">Pick a project to configure and run browser checks against its deployed website.</p>

        <div class="mt-6 bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                        <th class="px-4 py-2.5 font-medium">Project</th>
                        <th class="px-4 py-2.5 font-medium">Test Runs</th>
                        <th class="px-4 py-2.5 font-medium">Latest Result</th>
                        <th class="px-4 py-2.5 font-medium">Last Run</th>
                        <th class="px-4 py-2.5 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projects as $project)
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                            <td class="px-4 py-2.5 font-medium text-gray-900">{{ $project->name }}</td>
                            <td class="px-4 py-2.5 text-gray-500">{{ $project->test_runs_count }}</td>
                            <td class="px-4 py-2.5 text-gray-500">
                                @if ($project->latestTestRun)
                                    <span class="text-green-600">{{ $project->latestTestRun->passed }} pass</span> /
                                    <span class="text-amber-600">{{ $project->latestTestRun->warnings }} warn</span> /
                                    <span class="text-red-600">{{ $project->latestTestRun->failed }} fail</span> /
                                    <span class="text-gray-400">{{ $project->latestTestRun->not_testable }} n/t</span>
                                @else
                                    <span class="text-gray-400">No runs yet</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-gray-500">
                                {{ $project->latestTestRun?->created_at?->format('M d, Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @if ($project->latestTestRun)
                                        <a href="{{ route('projects.testing.show', [$project, $project->latestTestRun]) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                            View Results
                                        </a>
                                    @endif
                                    <a href="{{ route('projects.testing.create', $project) }}" class="text-gray-500 hover:text-gray-700 text-xs font-medium">
                                        {{ $project->latestTestRun ? 'Retest' : 'Configure Testing' }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">
                                No projects yet.
                                <a href="{{ route('projects.new') }}" class="block mt-2 text-blue-600 font-medium hover:underline">Create your first project &rarr;</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-dashboard-layout>
