<x-dashboard-layout title="Company Rules">
    <div class="p-8" x-data="knowledgeUploader()">
        <x-page-header title="Company Knowledge" subtitle="Upload a document for each section below. Every rule inside is detected by its code and stored in that section's table automatically." />

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

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($categories as $prefix => $category)
                <div class="bg-white border border-gray-200 rounded-xl p-4 transition hover:border-gray-300 hover:shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-{{ $category['color'] }}-50 flex items-center justify-center shrink-0">
                            <x-dashboard-icon name="doc" class="h-5 w-5 text-{{ $category['color'] }}-600" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 truncate">{{ $category['label'] }}</h3>
                            <p class="text-xs text-gray-400">
                                <span class="font-mono text-[10px] uppercase tracking-wide bg-gray-100 text-gray-500 rounded px-1 py-0.5">{{ $prefix }}</span>
                                {{ $category['count'] }} {{ Str::plural('rule', $category['count']) }}
                            </p>
                        </div>
                    </div>

                    <input type="file" id="knowledge-file-{{ $prefix }}" class="hidden"
                           accept=".pdf,.doc,.docx,.txt,.md"
                           @change="if ($event.target.files.length) startUpload('{{ $prefix }}', $event.target.files[0])">

                    <div class="mt-3"
                         x-show="cards['{{ $prefix }}'].phase === 'idle'"
                         @dragover.prevent="cards['{{ $prefix }}'].dragging = true"
                         @dragleave.prevent="cards['{{ $prefix }}'].dragging = false"
                         @drop.prevent="cards['{{ $prefix }}'].dragging = false; if ($event.dataTransfer.files.length) startUpload('{{ $prefix }}', $event.dataTransfer.files[0])"
                         @click="document.getElementById('knowledge-file-{{ $prefix }}').click()"
                         :class="cards['{{ $prefix }}'].dragging ? 'border-blue-400 bg-blue-50' : 'border-gray-200'"
                         class="border-2 border-dashed rounded-xl py-6 text-center transition cursor-pointer">
                        <p class="text-xs font-medium text-gray-600">Drag &amp; drop or <span class="text-blue-600 font-semibold">browse</span></p>
                        <p class="mt-1 text-[11px] text-gray-400">PDF, DOCX, TXT, MD</p>
                    </div>

                    <div class="mt-3" x-show="cards['{{ $prefix }}'].phase !== 'idle'">
                        <p class="text-xs font-medium text-gray-900 truncate" x-text="cards['{{ $prefix }}'].fileName"></p>

                        <div class="mt-2 h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300"
                                 :class="cards['{{ $prefix }}'].phase === 'error' ? 'bg-red-500' : (cards['{{ $prefix }}'].phase === 'done' ? 'bg-green-500' : 'bg-blue-600')"
                                 :style="`width: ${cards['{{ $prefix }}'].progress}%`"></div>
                        </div>

                        <p class="mt-2 text-[11px]" :class="cards['{{ $prefix }}'].phase === 'error' ? 'text-red-600' : 'text-gray-500'" x-text="cards['{{ $prefix }}'].statusText"></p>

                        <button x-show="cards['{{ $prefix }}'].phase === 'done' || cards['{{ $prefix }}'].phase === 'error'" type="button"
                                @click="reset('{{ $prefix }}')" class="mt-2 text-[11px] font-medium text-blue-600 hover:underline">
                            Upload another file
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8 bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Recent Uploads</h2>
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                        <th class="px-5 py-3 font-medium">Doc Name</th>
                        <th class="px-5 py-3 font-medium">Section</th>
                        <th class="px-5 py-3 font-medium">Date</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Extracted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50 transition">
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-2 text-gray-800 font-medium">
                                    <x-dashboard-icon name="doc" class="h-4 w-4 text-gray-400" />
                                    {{ $document->original_filename }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $categories[$document->category]['label'] ?? '—' }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $document->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$document->status" /></td>
                            <td class="px-5 py-3 text-gray-500">{{ $document->extracted_sections }} Sections</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-empty-state icon="doc" title="No documents uploaded yet" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function knowledgeUploader() {
            const prefixes = @json(array_keys($categories));
            const cards = {};
            prefixes.forEach((prefix) => {
                cards[prefix] = { dragging: false, phase: 'idle', progress: 0, fileName: '', statusText: '' };
            });

            return {
                cards,
                startUpload(prefix, file) {
                    const card = this.cards[prefix];
                    card.phase = 'uploading';
                    card.progress = 0;
                    card.fileName = file.name;
                    card.statusText = 'Uploading...';

                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('category', prefix);

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '{{ route('knowledge-base.upload') }}');
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);

                    xhr.upload.onprogress = (event) => {
                        if (event.lengthComputable) {
                            card.progress = Math.min(90, Math.round((event.loaded / event.total) * 90));
                            if (card.progress >= 90) {
                                card.phase = 'processing';
                                card.statusText = 'Reading the document and classifying rules...';
                            }
                        }
                    };

                    xhr.onload = () => {
                        let data = null;
                        try {
                            data = JSON.parse(xhr.responseText);
                        } catch (e) {
                            data = null;
                        }

                        if (xhr.status >= 200 && xhr.status < 300 && data) {
                            card.progress = 100;

                            if (data.status === 'error') {
                                card.phase = 'error';
                                card.statusText = data.error || 'Something went wrong while processing this file.';
                            } else {
                                card.phase = 'done';
                                card.statusText = `Stored ${data.total_rules} ${data.total_rules === 1 ? 'rule' : 'rules'}.`;
                                setTimeout(() => window.location.reload(), 1200);
                            }
                        } else {
                            card.phase = 'error';
                            card.statusText = 'Upload failed. Please try again.';
                        }
                    };

                    xhr.onerror = () => {
                        card.phase = 'error';
                        card.statusText = 'Upload failed. Please try again.';
                    };

                    xhr.send(formData);
                },
                reset(prefix) {
                    const card = this.cards[prefix];
                    card.phase = 'idle';
                    card.progress = 0;
                    card.fileName = '';
                    card.statusText = '';
                    document.getElementById('knowledge-file-' + prefix).value = '';
                },
            };
        }
    </script>
</x-dashboard-layout>
