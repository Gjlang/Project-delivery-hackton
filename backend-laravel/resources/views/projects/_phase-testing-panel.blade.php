@php
    $failingChecks = $latestChecks->filter(fn ($c) => in_array($c->status, ['FAIL', 'NEEDS_INFORMATION'], true));
    $testingPassed = $latestChecks->isNotEmpty() && $failingChecks->isEmpty();
@endphp

<div class="mt-4 rounded-xl border border-gray-100 bg-gray-50/60 p-4">
    <div class="flex items-center gap-2">
        <x-dashboard-icon name="flask" class="h-4 w-4 text-gray-400" />
        <h3 class="text-sm font-semibold text-gray-800">Testing Standards</h3>
        @if ($latestChecks->isNotEmpty())
            <x-status-badge :status="$testingPassed ? 'pass' : 'fail'" :label="$testingPassed ? 'Passing' : 'Blocked'" />
        @endif
    </div>

    @if (! $latestRun)
        <p class="mt-1.5 text-sm text-gray-500">No Playwright test run yet for this project. Enter a URL to run one.</p>

        <form method="POST" action="{{ route('projects.testing.store', $project) }}" class="mt-3 flex items-center gap-2">
            @csrf
            <input type="hidden" name="stay_on_phases" value="1">
            <input type="url" name="website_url" value="{{ old('website_url') }}" required placeholder="https://your-site.com"
                   class="flex-1 max-w-sm text-sm rounded-lg border-gray-300 focus:border-blue-400 focus:ring-blue-400">
            <button type="submit" class="px-4 py-2 text-xs font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 whitespace-nowrap">
                Run Test
            </button>
        </form>
    @else
        <div class="mt-3 bg-white rounded-lg border border-gray-100 px-4 py-3 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-sm">
            <a href="{{ route('projects.testing.show', [$project, $latestRun]) }}" class="text-blue-600 hover:text-blue-700 font-medium truncate max-w-xs">
                {{ $latestRun->website_url }}
            </a>
            <span class="text-green-600 font-medium">{{ $latestRun->passed }} passed</span>
            <span class="text-amber-600 font-medium">{{ $latestRun->warnings }} warnings</span>
            <span class="text-red-600 font-medium">{{ $latestRun->failed }} failed</span>
            <span class="text-gray-400 font-medium">{{ $latestRun->not_testable }} not testable</span>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('projects.phases.testing-check', $project) }}">
                @csrf
                <button type="submit" class="px-4 py-2 text-xs font-medium bg-gray-800 text-white rounded-lg hover:bg-gray-900">
                    Check Testing Standards
                </button>
            </form>

            <form method="POST" action="{{ route('projects.testing.store', $project) }}" class="flex items-center gap-2">
                @csrf
                <input type="hidden" name="stay_on_phases" value="1">
                <input type="url" name="website_url" value="{{ old('website_url', $latestRun->website_url) }}" required placeholder="https://your-site.com"
                       class="text-xs rounded-lg border-gray-300 focus:border-blue-400 focus:ring-blue-400">
                <button type="submit" class="px-4 py-2 text-xs font-medium bg-white border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50 whitespace-nowrap">
                    Run Another Test
                </button>
            </form>
        </div>

        @if ($latestChecks->isNotEmpty())
            <div class="mt-3 bg-white rounded-lg border border-gray-100 divide-y divide-gray-50 overflow-hidden">
                @foreach ($latestChecks as $check)
                    <div class="flex items-start gap-3 px-4 py-2.5 text-sm">
                        <x-status-badge :status="$check->status" class="shrink-0 w-32" />
                        <span class="text-gray-600">
                            <span class="font-medium text-gray-700">{{ $check->rule_code }}</span>
                            @if ($check->title) &middot; {{ $check->title }} @endif
                            @if ($check->reason) &mdash; {{ $check->reason }} @endif
                        </span>
                    </div>
                @endforeach
            </div>

            @if (! $testingPassed)
                <p class="mt-3 text-xs text-red-600 flex items-center gap-1.5">
                    <x-dashboard-icon name="warning" class="h-3.5 w-3.5 shrink-0" />
                    Testing Standards must pass before this phase can be marked done.
                </p>
            @endif
        @endif
    @endif
</div>
