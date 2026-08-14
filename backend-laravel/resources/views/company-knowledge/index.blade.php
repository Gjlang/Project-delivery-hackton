<x-dashboard-layout title="Company Knowledge">
    <div class="p-8" x-data="knowledgeManager()">
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

        {{-- Knowledge Base Library preview --}}
        <div class="mt-8 bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Knowledge Base Library</h2>
                <a href="{{ route('company-knowledge.library') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700">
                    View Full Library &rarr;
                </a>
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
                    @forelse ($documents->take(5) as $document)
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
                                @php
                                    $statusColor = match($document->status) {
                                        'indexed' => 'green',
                                        'error' => 'red',
                                        default => 'amber',
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-{{ $statusColor }}-600">
                                    <span class="h-1.5 w-1.5 rounded-full bg-{{ $statusColor }}-500"></span>
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

        @include('company-knowledge._details-panel')
        @include('company-knowledge._edit-modal')
    </div>
</x-dashboard-layout>
