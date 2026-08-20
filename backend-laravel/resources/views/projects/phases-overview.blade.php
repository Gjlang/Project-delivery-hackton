<x-dashboard-layout title="Phases">
    <div class="p-8">
        <x-page-header title="Phases" subtitle="Every project you've created, and where it stands in delivery." />

        @if ($rows->isEmpty())
            <div class="mt-6 bg-white border border-gray-200 rounded-xl p-10 text-center text-sm text-gray-400">
                No projects yet.
                <a href="{{ route('projects.new') }}" class="text-blue-600 hover:text-blue-700 font-medium">Create one &rarr;</a>
            </div>
        @else
            <div class="mt-6 space-y-3">
                @foreach ($rows as $row)
                    @php
                        $project = $row['project'];
                        $current = $row['current'];
                        $progress = $row['total_phases'] > 0 ? round(($row['done_phases'] / $row['total_phases']) * 100) : 0;
                    @endphp
                    <a href="{{ $row['total_phases'] > 0 ? route('projects.phases.index', $project) : route('projects.company-knowledge.index', $project) }}"
                       class="block bg-white border border-gray-200 rounded-xl p-5 hover:border-blue-300 hover:shadow-sm transition">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">{{ $project->name }}</h2>
                                @if ($current)
                                    <p class="mt-1 text-sm text-gray-500 flex items-center gap-2">
                                        <span>Phase {{ $current->phase_number }} of {{ $row['total_phases'] }}: {{ $current->phase_name }}</span>
                                        <x-status-badge :status="$current->status" :label="$current->status === 'done' ? 'All phases complete' : null" />
                                    </p>
                                @else
                                    <p class="mt-1 text-sm text-gray-400">No phases generated yet.</p>
                                @endif
                            </div>
                            <span class="text-sm font-medium text-gray-500 shrink-0">{{ $row['done_phases'] }}/{{ $row['total_phases'] }}</span>
                        </div>

                        @if ($row['total_phases'] > 0)
                            <div class="mt-3 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-blue-500 rounded-full" style="width: {{ $progress }}%"></div>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-dashboard-layout>
