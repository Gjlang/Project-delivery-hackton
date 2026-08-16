<x-dashboard-layout title="Test Results" :project="$project">
    <div class="p-8" x-data="{ activeEvidence: null }">
        <a href="{{ route('projects.testing.create', $project) }}" class="text-xs font-medium text-gray-400 hover:text-gray-600">&larr; Back to Testing</a>
        <div class="mt-2 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Run #{{ $run->id }} Results</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $run->website_url }} &middot; {{ $run->created_at->format('M d, Y H:i') }}</p>
            </div>
            <a href="{{ route('projects.testing.create', $project) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Retest Website
            </a>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">{{ session('status') }}</div>
        @endif

        {{-- Disclaimer (TS-032) --}}
        <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs px-4 py-3">
            Successful browser testing does not prove backend code, database infrastructure, server security, or other non-browser controls comply with company standards (TS-032). See "Not Testable" results below for what this run could not verify.
        </div>

        {{-- Summary cards --}}
        <div class="mt-6 grid grid-cols-2 sm:grid-cols-5 gap-4">
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-xs text-gray-500">Total Checks</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $run->executed_tests }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-xs text-gray-500">Passed</p>
                <p class="mt-1 text-2xl font-bold text-green-600">{{ $run->passed }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-xs text-gray-500">Warnings</p>
                <p class="mt-1 text-2xl font-bold text-amber-600">{{ $run->warnings }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-xs text-gray-500">Failed</p>
                <p class="mt-1 text-2xl font-bold text-red-600">{{ $run->failed }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-xs text-gray-500">Not Testable</p>
                <p class="mt-1 text-2xl font-bold text-gray-500">{{ $run->not_testable }}</p>
            </div>
        </div>

        @php $criticalFailures = $results->where('status', 'FAIL')->where('severity', 'critical'); @endphp
        @if ($criticalFailures->isNotEmpty())
            <div class="mt-4 rounded-lg bg-red-50 border border-red-300 text-red-800 text-sm px-4 py-3">
                <strong>{{ $criticalFailures->count() }} critical failure(s):</strong>
                {{ $criticalFailures->pluck('rule_code')->implode(', ') }}
            </div>
        @endif

        @if ($comparison !== null)
            <div class="mt-4 bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-xs font-semibold text-gray-700">Compared to Run #{{ $previousRun->id }}</p>
                @if (empty($comparison))
                    <p class="mt-1 text-xs text-gray-400">No status changes since the previous run.</p>
                @else
                    <ul class="mt-2 space-y-1 text-xs">
                        @foreach ($comparison as $c)
                            <li>{{ $c['rule_code'] }}: {{ $c['previous'] }} &rarr; <strong>{{ $c['latest'] }}</strong></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" class="mt-6 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500">Status</label>
                <select name="status" class="mt-1 text-sm rounded-md border-gray-300">
                    <option value="">All</option>
                    @foreach (['PASS','WARNING','FAIL','NOT_TESTABLE'] as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">Category</label>
                <select name="category" class="mt-1 text-sm rounded-md border-gray-300">
                    <option value="">All</option>
                    <option value="SC" @selected(($filters['category'] ?? '') === 'SC')>Security & Compliance</option>
                    <option value="TS" @selected(($filters['category'] ?? '') === 'TS')>Technical Standards</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">Severity</label>
                <select name="severity" class="mt-1 text-sm rounded-md border-gray-300">
                    <option value="">All</option>
                    @foreach (['critical','high','medium','low'] as $s)
                        <option value="{{ $s }}" @selected(($filters['severity'] ?? '') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Filter</button>
            @if (($filters['status'] ?? '') || ($filters['category'] ?? '') || ($filters['severity'] ?? ''))
                <a href="{{ route('projects.testing.show', [$project, $run]) }}" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
            @endif
        </form>

        {{-- Results --}}
        <div class="mt-4 space-y-3">
            @forelse ($results as $result)
                @php
                    $statusColor = match($result->status) {
                        'PASS' => 'green', 'WARNING' => 'amber', 'FAIL' => 'red', default => 'gray',
                    };
                @endphp
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden {{ $result->severity === 'critical' && $result->status === 'FAIL' ? 'ring-2 ring-red-300' : '' }}">
                    <details>
                        <summary class="flex items-center justify-between px-4 py-3 cursor-pointer select-none list-none">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-{{ $statusColor }}-50 text-{{ $statusColor }}-700">
                                    {{ str_replace('_', ' ', $result->status) }}
                                </span>
                                <span class="text-sm font-medium text-gray-900">{{ $result->rule_code }}</span>
                                @if ($result->severity === 'critical')
                                    <span class="text-xs font-medium text-red-600">CRITICAL</span>
                                @endif
                            </div>
                            <span class="text-xs text-gray-400">{{ $result->category }}</span>
                        </summary>

                        <div class="border-t border-gray-100 px-4 py-3 text-sm space-y-3">
                            <p class="text-xs text-gray-400">Applicability: {{ str_replace('_', ' ', $result->applicability_status) }}</p>

                            @if ($result->tested_page)
                                <p><span class="text-gray-500">Tested page:</span> {{ $result->tested_page }}</p>
                            @endif
                            @if ($result->expected_behavior)
                                <p><span class="text-gray-500">Expected:</span> {{ $result->expected_behavior }}</p>
                            @endif
                            @if ($result->observed_behavior)
                                <p><span class="text-gray-500">Observed:</span> {{ $result->observed_behavior }}</p>
                            @endif

                            @if ($result->ai_explanation || $result->ai_impact || $result->ai_recommendation)
                                <div class="rounded-lg bg-blue-50 border border-blue-100 p-3 space-y-1">
                                    <p class="text-xs font-semibold text-blue-700">AI Feedback (supplemental, does not change the result above)</p>
                                    @if ($result->ai_explanation)<p class="text-xs text-blue-900">{{ $result->ai_explanation }}</p>@endif
                                    @if ($result->ai_impact)<p class="text-xs text-blue-900"><strong>Impact:</strong> {{ $result->ai_impact }}</p>@endif
                                    @if ($result->ai_recommendation)<p class="text-xs text-blue-900"><strong>Recommendation:</strong> {{ $result->ai_recommendation }}</p>@endif
                                </div>
                            @endif

                            @if (! empty($result->evidence))
                                <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600 space-y-1">
                                    <p class="font-semibold text-gray-700">Evidence</p>
                                    @foreach (['url','final_url','http_status','browser','selector'] as $key)
                                        @if (! empty($result->evidence[$key]))
                                            <p>{{ ucfirst(str_replace('_',' ',$key)) }}: {{ $result->evidence[$key] }}</p>
                                        @endif
                                    @endforeach
                                    @if (! empty($result->evidence['console_errors']))
                                        <p>Console/JS errors: {{ implode('; ', array_slice($result->evidence['console_errors'], 0, 3)) }}</p>
                                    @endif
                                    @if (! empty($result->evidence['network_errors']))
                                        <p>Network errors: {{ implode('; ', array_slice($result->evidence['network_errors'], 0, 3)) }}</p>
                                    @endif
                                    @if (! empty($result->evidence['screenshot']))
                                        <a href="{{ route('projects.testing.evidence', [$project, $run, $result]) }}" target="_blank" class="inline-block mt-2 text-blue-600 hover:underline">View Screenshot &rarr;</a>
                                    @endif
                                </div>
                            @endif

                            <p class="text-xs text-gray-400">Rule source: {{ $result->rule_code }}</p>
                        </div>
                    </details>
                </div>
            @empty
                <div class="bg-white border border-dashed border-gray-300 rounded-xl p-10 text-center text-sm text-gray-400">
                    No results match these filters.
                </div>
            @endforelse
        </div>
    </div>
</x-dashboard-layout>
