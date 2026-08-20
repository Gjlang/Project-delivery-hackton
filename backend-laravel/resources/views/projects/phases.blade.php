<x-dashboard-layout title="Project Phases" :project="$project">
    <div class="p-8" x-data="{ confirmModal: { open: false, phaseNumber: null, phaseName: null, action: null } }">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $project->name }} &middot; Phases</h1>
                <p class="mt-1 text-sm text-gray-500">Track delivery progress phase by phase. Mark the current phase done to advance to the next.</p>
            </div>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($phases->isEmpty())
            <div class="mt-6 bg-white border border-gray-200 rounded-xl p-10 text-center text-sm text-gray-400">
                No phases yet. Phases are generated automatically from the AI project plan when a project is created.
            </div>
        @else
            {{-- Progress strip --}}
            <div class="mt-6 bg-white border border-gray-200 rounded-xl p-5">
                <div class="flex items-center">
                    @foreach ($phases as $phase)
                        @php
                            $dotColor = match($phase->status) {
                                'done' => 'bg-green-500 text-white',
                                'in_progress' => 'bg-blue-600 text-white ring-4 ring-blue-100',
                                default => 'bg-gray-100 text-gray-400',
                            };
                        @endphp
                        <div class="flex items-center {{ ! $loop->last ? 'flex-1' : '' }}">
                            <div class="flex flex-col items-center gap-1.5 shrink-0">
                                <div class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-semibold {{ $dotColor }}">
                                    @if ($phase->status === 'done')
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    @else
                                        {{ $phase->phase_number }}
                                    @endif
                                </div>
                                <span class="text-[11px] text-gray-500 text-center max-w-[80px] leading-tight">{{ $phase->phase_name }}</span>
                            </div>
                            @if (! $loop->last)
                                <div class="flex-1 h-0.5 mx-1 mb-4 {{ $phase->status === 'done' ? 'bg-green-400' : 'bg-gray-200' }}"></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 space-y-3">
                @foreach ($phases as $phase)
                    @php
                        $statusMeta = match($phase->status) {
                            'done' => ['label' => 'Done', 'color' => 'green'],
                            'in_progress' => ['label' => 'In Progress', 'color' => 'blue'],
                            default => ['label' => 'Not Started', 'color' => 'gray'],
                        };
                    @endphp

                    @if ($phase->status === 'done')
                        {{-- Collapsed summary row for completed phases --}}
                        <div class="bg-white border border-gray-200 rounded-xl px-5 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="h-6 w-6 rounded-full bg-green-50 text-green-600 flex items-center justify-center text-xs font-semibold">{{ $phase->phase_number }}</div>
                                <span class="text-sm font-medium text-gray-700">{{ $phase->phase_name }}</span>
                            </div>
                            <span class="text-xs text-gray-400">
                                Completed {{ $phase->completed_at?->format('M d, Y') }}
                            </span>
                        </div>
                    @else
                        <div class="bg-white border rounded-xl p-5 {{ $phase->status === 'in_progress' ? 'border-blue-300 ring-1 ring-blue-100' : 'border-gray-200' }}">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-4">
                                    <div class="mt-0.5 h-8 w-8 shrink-0 rounded-full flex items-center justify-center text-sm font-semibold
                                                bg-{{ $statusMeta['color'] }}-50 text-{{ $statusMeta['color'] }}-600">
                                        {{ $phase->phase_number }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h2 class="text-base font-semibold text-gray-900">{{ $phase->phase_name }}</h2>
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-{{ $statusMeta['color'] }}-600">
                                                <span class="h-1.5 w-1.5 rounded-full bg-{{ $statusMeta['color'] }}-500"></span>
                                                {{ $statusMeta['label'] }}
                                            </span>
                                        </div>

                                        @if ($phase->duration_days)
                                            <p class="mt-1 text-xs text-gray-500">
                                                Estimated {{ $phase->duration_days }} day{{ $phase->duration_days === 1 ? '' : 's' }}
                                                @if ($phase->duration_reason)
                                                    &middot; {{ $phase->duration_reason }}
                                                @endif
                                            </p>
                                        @endif

                                        @if (! empty($phase->tasks))
                                            <ul class="mt-3 space-y-1.5">
                                                @foreach ($phase->tasks as $task)
                                                    <li class="text-sm text-gray-600 flex items-start gap-2">
                                                        <span class="mt-1.5 h-1 w-1 rounded-full bg-gray-300 shrink-0"></span>
                                                        <span>
                                                            <span class="font-medium text-gray-700">{{ $task['name'] ?? 'Task' }}</span>
                                                            @if (! empty($task['description']))
                                                                &mdash; {{ $task['description'] }}
                                                            @endif
                                                        </span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif

                                        @if ($phase->phase_number === 5 && $phase->status === 'in_progress')
                                            @include('projects._phase-testing-panel', ['project' => $project, 'latestRun' => $latestRun, 'latestChecks' => $latestChecks])
                                        @endif
                                    </div>
                                </div>

                                @if ($phase->status === 'in_progress')
                                    <button type="button"
                                            @click="confirmModal = { open: true, phaseNumber: {{ $phase->phase_number }}, phaseName: @js($phase->phase_name), action: '{{ route('projects.phases.complete', [$project, $phase]) }}' }"
                                            class="shrink-0 px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 whitespace-nowrap">
                                        Mark Phase Done
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Mark Phase Done confirmation modal --}}
        <div x-show="confirmModal.open" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
             @keydown.escape.window="confirmModal.open = false">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6" @click.outside="confirmModal.open = false">
                <h3 class="text-lg font-semibold text-gray-900">Mark phase done?</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Phase <span x-text="confirmModal.phaseNumber"></span> ("<span x-text="confirmModal.phaseName"></span>") will be marked complete and the next phase will start automatically. This can't be undone.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="confirmModal.open = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">
                        Cancel
                    </button>
                    <form method="POST" :action="confirmModal.action">
                        @csrf
                        <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Yes, Mark Done
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
