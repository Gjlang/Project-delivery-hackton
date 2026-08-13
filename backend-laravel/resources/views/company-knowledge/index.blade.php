<x-dashboard-layout title="Company Knowledge">
    <div class="p-8" x-data="documentPanel()">
        <h1 class="text-2xl font-bold text-gray-900">Company Knowledge</h1>
        <p class="mt-1 text-sm text-gray-500">Upload the information that the AI should understand before generating a project plan.
            Every section is optional, but more complete information produces a more accurate plan.</p>

        @if (session('status'))
            <div class="mt-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif
        @error('file')
            <div class="mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                {{ $message }}
            </div>
        @enderror

        {{-- Category cards --}}
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($categories as $key => $category)
                @php
                    $badge = match(true) {
                        $category['total'] === 0 => ['Empty', 'bg-gray-100 text-gray-500'],
                        $category['indexed'] === $category['total'] => [$category['total'].' Docs · 100% Processed', 'bg-green-50 text-green-700'],
                        default => [$category['total'].' Docs · Processing', 'bg-amber-50 text-amber-700'],
                    };
                @endphp

                <div class="bg-white border border-gray-200 rounded-xl p-5 flex flex-col">
                    <div class="flex items-start justify-between">
                        <div class="h-9 w-9 rounded-lg bg-{{ $category['color'] }}-50 flex items-center justify-center">
                            <x-dashboard-icon name="doc" class="h-5 w-5 text-{{ $category['color'] }}-600" />
                        </div>
                        <span class="text-xs font-medium px-2 py-1 rounded-full {{ $badge[1] }}">{{ $badge[0] }}</span>
                    </div>

                    <h3 class="mt-3 text-sm font-semibold text-gray-900">{{ $category['label'] }}</h3>
                    <p class="mt-1 text-xs text-gray-500 flex-1">{{ $category['description'] }}</p>

                    <form method="POST" action="{{ route('company-knowledge.documents.store') }}" enctype="multipart/form-data"
                          class="mt-4"
                          x-data="{ dragging: false }"
                          @submit="if(!$refs.fileInput.files.length){ $event.preventDefault(); }">
                        @csrf
                        <input type="hidden" name="category" value="{{ $key }}">
                        <input type="file" name="file" x-ref="fileInput" class="hidden"
                               accept=".pdf,.doc,.docx,.txt,.md"
                               @change="$event.target.form.submit()">

                        <div @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                             @drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $event.target.closest('form').submit();"
                             :class="dragging ? 'border-blue-400 bg-blue-50' : 'border-gray-200'"
                             class="border border-dashed rounded-lg py-4 text-center text-xs text-gray-400 transition">
                            <p>Drag &amp; Drop files</p>
                            <p class="mt-1">
                                <button type="button" @click="$refs.fileInput.click()" class="text-blue-600 font-medium hover:underline">Upload</button>
                                or
                                <span class="text-gray-400" title="Coming soon">Enter text</span>
                            </p>
                            <p class="mt-2 text-[11px] text-gray-300">PDF, DOCX, TXT, MD</p>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>

        {{-- Knowledge Base Library --}}
        <div class="mt-8 bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Knowledge Base Library</h2>
                <span class="text-xs font-medium text-blue-600">Filter Library</span>
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                        <th class="px-5 py-3 font-medium">Doc Name</th>
                        <th class="px-5 py-3 font-medium">Category</th>
                        <th class="px-5 py-3 font-medium">Version</th>
                        <th class="px-5 py-3 font-medium">Date</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Extracted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50 cursor-pointer"
                            @click="open({{ $document->id }})">
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-2 text-blue-600 font-medium">
                                    <x-dashboard-icon name="doc" class="h-4 w-4" />
                                    {{ $document->original_filename }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $categories[$document->category]['label'] ?? $document->category }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $document->version }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $document->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1 text-xs font-medium {{ $document->status === 'indexed' ? 'text-green-600' : 'text-amber-600' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $document->status === 'indexed' ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                                    {{ ucfirst($document->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $document->extracted_sections }} Sections</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-400">No documents uploaded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Details slide-over panel --}}
        <div x-show="visible" x-cloak class="fixed inset-0 z-40" style="display:none">
            <div class="absolute inset-0 bg-black/20" @click="close()"></div>

            <div x-show="visible" x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                 class="absolute right-0 top-0 h-full w-full max-w-sm bg-white border-l border-gray-200 shadow-xl overflow-y-auto">

                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Document Details</h3>
                    <button @click="close()" class="text-gray-400 hover:text-gray-600">
                        <x-dashboard-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <template x-if="doc">
                    <div class="px-5 py-4 space-y-5 text-sm">
                        <div>
                            <p class="text-xs text-gray-400">Meta Info</p>
                            <dl class="mt-2 space-y-1">
                                <div class="flex justify-between"><dt class="text-gray-500">Title</dt><dd class="font-medium text-gray-900" x-text="doc.title"></dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Category</dt><dd class="text-gray-700" x-text="doc.category"></dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Version</dt><dd class="text-gray-700" x-text="doc.version"></dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Date</dt><dd class="text-gray-700" x-text="doc.date"></dd></div>
                            </dl>
                        </div>

                        <div>
                            <p class="text-xs text-gray-400">Extracted Summary</p>
                            <p class="mt-2 text-gray-600" x-text="doc.extracted_summary || 'Not analyzed yet.'"></p>
                        </div>

                        <div class="pt-2 border-t border-gray-100">
                            <button @click="reindex()" class="w-full bg-blue-600 text-white text-sm font-medium rounded-lg py-2.5 hover:bg-blue-700">
                                Re-Index Document
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        function documentPanel() {
            return {
                visible: false,
                doc: null,
                open(id) {
                    this.visible = true;
                    this.doc = null;
                    fetch(`/company-knowledge/documents/${id}`)
                        .then(r => r.json())
                        .then(data => this.doc = data);
                },
                close() {
                    this.visible = false;
                },
                reindex() {
                    if (!this.doc) return;
                    fetch(`/company-knowledge/documents/${this.doc.id}/reindex`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    }).then(() => window.location.reload());
                },
            }
        }
    </script>
</x-dashboard-layout>
