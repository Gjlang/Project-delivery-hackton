<x-dashboard-layout title="AI Analysis">
    <div class="p-8" x-data="{ tab: '{{ collect($categories)->keys()->first() }}', ...knowledgeManager() }">
        <h1 class="text-2xl font-bold text-gray-900">AI Analysis</h1>
        <p class="mt-1 text-sm text-gray-500">
            Review the key points, categorized information, and full parsed text extracted from every processed document before it's used elsewhere in the system.
        </p>

        @if (session('status'))
            <div class="mt-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        {{-- Category tabs --}}
        <div class="mt-6 border-b border-gray-200">
            <nav class="flex flex-wrap gap-1 -mb-px">
                @foreach ($categories as $key => $category)
                    <button type="button" @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2.5 text-sm font-medium border-b-2 transition">
                        {{ $category['label'] }}
                        <span class="ml-1 text-xs text-gray-400">({{ $category['documents']->count() }})</span>
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Category panels --}}
        @foreach ($categories as $key => $category)
            <div x-show="tab === '{{ $key }}'" x-cloak class="mt-6 space-y-8">
                @if ($category['documents']->isEmpty())
                    <div class="border border-dashed border-gray-300 rounded-xl p-12 text-center text-gray-400 text-sm">
                        No documents processed in {{ $category['label'] }} yet.
                        <a href="{{ route('company-knowledge.index') }}" class="block mt-2 text-blue-600 font-medium hover:underline">
                            Upload one &rarr;
                        </a>
                    </div>
                @else
                    {{-- 1. Key Points --}}
                    <section>
                        <h2 class="text-sm font-semibold text-gray-900">Key Points</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Pooled from every document in this category.</p>

                        @if ($category['key_points']->isEmpty())
                            <p class="mt-3 text-sm text-gray-400">No clear rule-style statements were detected in this category's documents.</p>
                        @else
                            <ul class="mt-3 space-y-2">
                                @foreach ($category['key_points'] as $point)
                                    <li class="flex items-start gap-2 rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-sm">
                                        <svg class="h-4 w-4 shrink-0 text-green-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        <span class="text-gray-700 flex-1">{{ $point['text'] }}</span>
                                        <span class="text-xs text-gray-400 shrink-0">{{ $point['source'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    {{-- 3. Categorized Information: documents in this category --}}
                    <section>
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-900">Documents in {{ $category['label'] }}</h2>
                            <a href="{{ route('company-knowledge.index') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700">
                                + Upload Document
                            </a>
                        </div>
                        <div class="mt-3 bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                                        <th class="px-4 py-2.5 font-medium">Doc Name</th>
                                        <th class="px-4 py-2.5 font-medium">Version</th>
                                        <th class="px-4 py-2.5 font-medium">Status</th>
                                        <th class="px-4 py-2.5 font-medium">Words</th>
                                        <th class="px-4 py-2.5 font-medium">Key Points</th>
                                        <th class="px-4 py-2.5 font-medium text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($category['documents'] as $document)
                                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50 cursor-pointer"
                                            @click="open({{ $document->id }})">
                                            <td class="px-4 py-2.5 text-gray-900 font-medium">{{ $document->original_filename }}</td>
                                            <td class="px-4 py-2.5 text-gray-500">{{ $document->version }}</td>
                                            <td class="px-4 py-2.5">
                                                @php $statusColor = $document->status === 'error' ? 'red' : 'green'; @endphp
                                                <span class="inline-flex items-center gap-1 text-xs font-medium text-{{ $statusColor }}-600">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-{{ $statusColor }}-500"></span>
                                                    {{ ucfirst($document->status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5 text-gray-500">{{ $document->word_count }}</td>
                                            <td class="px-4 py-2.5 text-gray-500">{{ count($document->extracted_rules ?? []) }}</td>
                                            <td class="px-4 py-2.5" @click.stop>
                                                <div class="flex items-center justify-end gap-3">
                                                    <button type="button"
                                                            @click="startEdit({ id: {{ $document->id }}, title: @js($document->title), category: @js($document->category), version: @js($document->version), filename: @js($document->original_filename) })"
                                                            class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                                        Edit
                                                    </button>
                                                    <a href="{{ Illuminate\Support\Facades\Storage::url($document->path) }}" target="_blank"
                                                       class="text-gray-500 hover:text-gray-700 text-xs font-medium">
                                                        View
                                                    </a>
                                                    <form method="POST" action="{{ route('company-knowledge.documents.destroy', $document) }}"
                                                          onsubmit="return confirm('Delete &quot;{{ addslashes($document->original_filename) }}&quot;? This cannot be undone.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {{-- 2. Parsed Content --}}
                    <section>
                        <h2 class="text-sm font-semibold text-gray-900">Parsed Content</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Full text extracted from each document.</p>

                        <div class="mt-3 space-y-3">
                            @foreach ($category['documents'] as $document)
                                <details class="group bg-white border border-gray-200 rounded-xl">
                                    <summary class="flex items-center justify-between px-4 py-3 cursor-pointer select-none list-none">
                                        <span class="flex items-center gap-2 text-sm font-medium text-gray-900">
                                            <x-dashboard-icon name="doc" class="h-4 w-4 text-gray-400" />
                                            {{ $document->original_filename }}
                                        </span>
                                        <svg class="h-4 w-4 text-gray-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                    </summary>
                                    <div class="border-t border-gray-100 px-4 py-3">
                                        @if ($document->parsed_text)
                                            <pre class="max-h-96 overflow-y-auto whitespace-pre-wrap text-xs text-gray-600">{{ $document->parsed_text }}</pre>
                                        @else
                                            <p class="text-xs text-gray-400">No text could be extracted from this document.</p>
                                        @endif
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        @endforeach

        @include('company-knowledge._details-panel')
        @include('company-knowledge._edit-modal')
    </div>
</x-dashboard-layout>
