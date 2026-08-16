<x-dashboard-layout title="Company Rules">
    <div class="p-8" x-data="companyRulesPage()" x-init="load()">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Company Rules</h1>
                <p class="mt-1 text-sm text-gray-500">The rulebook every project is checked against -- configured once, reused by every project in this company.</p>
            </div>
        </div>

        <div class="mt-6 rounded-xl border p-4 flex items-center justify-between
                    {{ $readiness['status'] === 'READY' ? 'border-green-200 bg-green-50' : ($readiness['status'] === 'READY_WITH_WARNINGS' ? 'border-amber-200 bg-amber-50' : ($readiness['status'] === 'PROCESSING' ? 'border-blue-200 bg-blue-50' : 'border-red-200 bg-red-50')) }}">
            <div>
                <div class="text-sm font-semibold
                            {{ $readiness['status'] === 'READY' ? 'text-green-700' : ($readiness['status'] === 'READY_WITH_WARNINGS' ? 'text-amber-700' : ($readiness['status'] === 'PROCESSING' ? 'text-blue-700' : 'text-red-700')) }}">
                    Status: {{ str_replace('_', ' ', $readiness['status']) }}
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    {{ $readiness['active_rule_count'] }} active rules
                    @if ($readiness['processing_chunk_count'] > 0)
                        &middot; {{ $readiness['processing_chunk_count'] }} chunks still embedding
                    @endif
                </div>
                @foreach ($readiness['warnings'] as $warning)
                    <div class="mt-1 text-xs text-amber-700">{{ $warning }}</div>
                @endforeach
            </div>
            @if ($readiness['status'] === 'NOT_CONFIGURED')
                <div class="text-sm text-red-700">Set up company rules before creating projects with AI chat.</div>
            @endif
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <input type="text" x-model="filters.search" @input.debounce.400ms="load()" placeholder="Search rules..."
                   class="text-sm rounded-md border-gray-200 focus:border-blue-400 focus:ring-blue-400" />
            <select x-model="filters.category" @change="load()" class="text-sm rounded-md border-gray-200 focus:border-blue-400 focus:ring-blue-400">
                <option value="">All categories</option>
                <option value="BR">BR &mdash; Business Rules</option>
                <option value="CP">CP &mdash; Company Policies</option>
                <option value="EW">EW &mdash; Employee Rules</option>
                <option value="SC">SC &mdash; Security &amp; Compliance</option>
                <option value="TS">TS &mdash; Technical Standards</option>
                <option value="AG">AG &mdash; Approval &amp; Governance</option>
            </select>
            <select x-model="filters.status" @change="load()" class="text-sm rounded-md border-gray-200 focus:border-blue-400 focus:ring-blue-400">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="draft">Draft</option>
                <option value="inactive">Inactive</option>
                <option value="archived">Archived</option>
            </select>
            <span class="text-xs text-gray-400" x-show="loading">Loading&hellip;</span>
        </div>

        <div class="mt-4 bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                        <th class="px-4 py-2.5 font-medium">Code</th>
                        <th class="px-4 py-2.5 font-medium">Title</th>
                        <th class="px-4 py-2.5 font-medium">Category</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="px-4 py-2.5 font-medium">Version</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="rule in rules" :key="rule.id">
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-700" x-text="rule.rule_code"></td>
                            <td class="px-4 py-2.5 font-medium text-gray-900" x-text="rule.title"></td>
                            <td class="px-4 py-2.5 text-gray-500" x-text="rule.category?.code"></td>
                            <td class="px-4 py-2.5">
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                      :class="rule.status === 'active' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      x-text="rule.status"></span>
                            </td>
                            <td class="px-4 py-2.5 text-gray-500" x-text="rule.version"></td>
                        </tr>
                    </template>
                    <tr x-show="!loading && rules.length === 0">
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">No rules match these filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function companyRulesPage() {
            return {
                rules: [],
                loading: false,
                filters: { search: '', category: '', status: '' },
                load() {
                    this.loading = true;
                    const params = new URLSearchParams();
                    if (this.filters.search) params.set('search', this.filters.search);
                    if (this.filters.category) params.set('category', this.filters.category);
                    if (this.filters.status) params.set('status', this.filters.status);

                    fetch(`/company-rules?${params.toString()}`)
                        .then(r => r.json())
                        .then(data => { this.rules = data.data ?? []; this.loading = false; })
                        .catch(() => { this.loading = false; });
                },
            }
        }
    </script>
</x-dashboard-layout>
