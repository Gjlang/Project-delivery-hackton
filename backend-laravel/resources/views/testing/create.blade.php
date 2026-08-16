<x-dashboard-layout title="Website Testing" :project="$project">
    <div class="p-8 max-w-4xl">
        <a href="{{ route('projects.index') }}" class="text-xs font-medium text-gray-400 hover:text-gray-600">&larr; All Projects</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">{{ $project->name }} &middot; Website Testing</h1>
        <p class="mt-1 text-sm text-gray-500">
            Run browser checks against your deployed website for the active Security &amp; Compliance and Technical Standards rules.
            Supplying a website URL is what makes this project eligible for testing right now (full project-type classification isn't built yet).
        </p>

        @if (session('status'))
            <div class="mt-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('projects.testing.store', $project) }}" class="mt-6 space-y-6">
            @csrf

            <section class="bg-white border border-gray-200 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-gray-900">Website URL</h2>
                <input type="url" name="website_url" required placeholder="https://example.com"
                       class="mt-2 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400" value="{{ old('website_url') }}">
            </section>

            <details class="bg-white border border-gray-200 rounded-xl p-5">
                <summary class="text-sm font-semibold text-gray-900 cursor-pointer">Optional Testing Context</summary>
                <p class="mt-1 text-xs text-gray-400">Nothing here is required. Rules that need context you don't supply will be reported as Not Testable (insufficient context) rather than skipped silently.</p>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Protected routes (one per line)</label>
                        <textarea name="protected_routes" rows="3" class="mt-1 w-full text-sm font-mono rounded-md border-gray-300" placeholder="/dashboard">{{ old('protected_routes') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Admin routes (one per line)</label>
                        <textarea name="admin_routes" rows="3" class="mt-1 w-full text-sm font-mono rounded-md border-gray-300" placeholder="/admin">{{ old('admin_routes') }}</textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-xs font-medium text-gray-500">Login URL</label>
                    <input type="text" name="login_url" class="mt-1 w-full text-sm rounded-md border-gray-300" placeholder="/login" value="{{ old('login_url') }}">
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="border border-gray-100 rounded-lg p-3">
                        <p class="text-xs font-medium text-gray-700 mb-2">Valid test credentials</p>
                        <input type="text" name="valid_username" placeholder="Username / email" class="w-full text-sm rounded-md border-gray-300 mb-2">
                        <input type="password" name="valid_password" placeholder="Password" autocomplete="new-password" class="w-full text-sm rounded-md border-gray-300">
                    </div>
                    <div class="border border-gray-100 rounded-lg p-3">
                        <p class="text-xs font-medium text-gray-700 mb-2">Invalid test credentials (for login-rejection test)</p>
                        <input type="text" name="invalid_username" placeholder="Username / email" class="w-full text-sm rounded-md border-gray-300 mb-2">
                        <input type="password" name="invalid_password" placeholder="Password" autocomplete="new-password" class="w-full text-sm rounded-md border-gray-300">
                    </div>
                </div>
                <p class="mt-1 text-[11px] text-gray-400">Credentials are used only in-memory for this run and are never stored in the database, logs, or the vector index.</p>

                <div class="mt-4">
                    <label class="block text-xs font-medium text-gray-500">Role accounts (role|username|password, one per line)</label>
                    <textarea name="role_accounts" rows="2" class="mt-1 w-full text-sm font-mono rounded-md border-gray-300" placeholder="admin|admin@example.com|pass123">{{ old('role_accounts') }}</textarea>
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Critical pages (one per line)</label>
                        <textarea name="critical_pages" rows="3" class="mt-1 w-full text-sm font-mono rounded-md border-gray-300" placeholder="/">{{ old('critical_pages') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Required navigation labels (one per line)</label>
                        <textarea name="required_navigation" rows="3" class="mt-1 w-full text-sm font-mono rounded-md border-gray-300" placeholder="Home">{{ old('required_navigation') }}</textarea>
                    </div>
                </div>

                <div class="mt-4 border border-gray-100 rounded-lg p-3">
                    <p class="text-xs font-medium text-gray-700 mb-2">Test form (for required-field / invalid-input checks)</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="text" name="test_form_page" placeholder="Page path, e.g. /contact" class="text-sm rounded-md border-gray-300">
                        <input type="text" name="test_form_submit_selector" placeholder="Submit selector, e.g. button[type=submit]" class="text-sm rounded-md border-gray-300">
                        <input type="text" name="test_form_required_field_selector" placeholder="Required field selector, e.g. #email" class="text-sm rounded-md border-gray-300">
                        <input type="text" name="test_form_invalid_value" placeholder="Invalid test value, e.g. not-an-email" class="text-sm rounded-md border-gray-300">
                    </div>
                    <label class="mt-2 flex items-center gap-2 text-xs text-gray-500">
                        <input type="checkbox" name="allow_duplicate_submission_test" value="1">
                        Allow duplicate-submission testing on this form (only enable for a safe, non-production test form)
                    </label>
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Browsers</label>
                        <div class="mt-1 flex gap-4 text-sm text-gray-700">
                            <label class="flex items-center gap-1"><input type="checkbox" name="browsers[]" value="chromium" checked> Chromium</label>
                            <label class="flex items-center gap-1"><input type="checkbox" name="browsers[]" value="firefox"> Firefox</label>
                            <label class="flex items-center gap-1"><input type="checkbox" name="browsers[]" value="webkit"> WebKit</label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Timeout (ms)</label>
                        <input type="number" name="timeout_ms" value="15000" min="2000" max="60000" class="mt-1 w-full text-sm rounded-md border-gray-300">
                    </div>
                </div>
            </details>

            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Run Test
            </button>
        </form>

        <div class="mt-10">
            <h2 class="text-sm font-semibold text-gray-900">Test History</h2>
            <div class="mt-3 bg-white border border-gray-200 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                            <th class="px-4 py-2.5 font-medium">Run</th>
                            <th class="px-4 py-2.5 font-medium">Website</th>
                            <th class="px-4 py-2.5 font-medium">Status</th>
                            <th class="px-4 py-2.5 font-medium">Pass / Warn / Fail / N/T</th>
                            <th class="px-4 py-2.5 font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($runs as $run)
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('projects.testing.show', [$project, $run]) }}'">
                                <td class="px-4 py-2.5 font-medium text-blue-600">
                                    Run #{{ $run->id }}
                                    @if ($loop->first)<span class="ml-1 text-xs text-green-600">(latest)</span>@endif
                                </td>
                                <td class="px-4 py-2.5 text-gray-500">{{ $run->website_url }}</td>
                                <td class="px-4 py-2.5 text-gray-500">{{ ucfirst($run->status) }}</td>
                                <td class="px-4 py-2.5 text-gray-500">{{ $run->passed }} / {{ $run->warnings }} / {{ $run->failed }} / {{ $run->not_testable }}</td>
                                <td class="px-4 py-2.5 text-gray-500">{{ $run->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">No test runs yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-dashboard-layout>
