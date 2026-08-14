{{-- Details slide-over panel. Requires x-data="knowledgeManager()" on an ancestor. --}}
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
                        <div class="flex justify-between"><dt class="text-gray-500">Category</dt><dd class="text-gray-700" x-text="doc.category_label"></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Version</dt><dd class="text-gray-700" x-text="doc.version"></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Date</dt><dd class="text-gray-700" x-text="doc.date"></dd></div>
                    </dl>
                </div>

                <template x-if="doc.parse_error">
                    <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs px-3 py-2" x-text="'Parsing failed: ' + doc.parse_error"></div>
                </template>

                <div>
                    <p class="text-xs text-gray-400">Extracted Summary</p>
                    <p class="mt-2 text-gray-600" x-text="doc.extracted_summary || 'Not analyzed yet.'"></p>
                    <p class="mt-1 text-xs text-gray-400" x-show="doc.word_count" x-text="doc.word_count + ' words scanned'"></p>
                </div>

                <div x-show="doc.extracted_rules && doc.extracted_rules.length">
                    <p class="text-xs text-gray-400" x-text="'Found Rules (' + (doc.extracted_rules ? doc.extracted_rules.length : 0) + ')'"></p>
                    <ul class="mt-2 space-y-2">
                        <template x-for="rule in doc.extracted_rules" :key="rule">
                            <li class="flex items-start gap-2 rounded-lg bg-gray-50 px-3 py-2 text-gray-700 text-xs">
                                <svg class="h-4 w-4 shrink-0 text-green-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                <span x-text="rule"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                <div x-show="doc.keyword_hits && Object.keys(doc.keyword_hits).length">
                    <p class="text-xs text-gray-400">Matched Keywords</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <template x-for="(count, word) in doc.keyword_hits" :key="word">
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 text-blue-700 text-[11px] font-medium px-2 py-1">
                                <span x-text="word"></span>
                                <span class="text-blue-400" x-text="'×' + count"></span>
                            </span>
                        </template>
                    </div>
                </div>

                <div x-show="doc.parsed_text">
                    <details class="group">
                        <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600 select-none">
                            View Full Parsed Text
                        </summary>
                        <pre class="mt-2 max-h-64 overflow-y-auto whitespace-pre-wrap rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600" x-text="doc.parsed_text"></pre>
                    </details>
                </div>

                <div class="pt-2 border-t border-gray-100 flex gap-2">
                    <button @click="reindex()" class="flex-1 bg-blue-600 text-white text-sm font-medium rounded-lg py-2.5 hover:bg-blue-700">
                        Re-Index Document
                    </button>
                    <button @click="close(); startEdit({ id: doc.id, title: doc.title, category: doc.category, version: doc.version, filename: doc.original_filename })"
                            class="px-4 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg py-2.5 hover:bg-gray-200">
                        Edit
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>
