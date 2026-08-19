<x-dashboard-layout title="Create Project with AI">
    <div class="h-full flex flex-col" x-data="projectCreationChat()" x-init="init()">
        <div class="px-8 py-5 border-b border-gray-200 bg-white flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Create Project with AI</h1>
                <p class="text-xs text-gray-500 mt-0.5">Describe your project naturally -- I'll check it against your company's rules as we go.</p>
            </div>
            <a href="{{ route('projects.new.legacy') }}" class="text-xs text-gray-400 hover:text-gray-600">Use the plain form instead &rarr;</a>
        </div>

        <div class="flex-1 grid grid-cols-1 lg:grid-cols-[220px_1fr_1fr] min-h-0">
            {{-- Conversation history --}}
            <div class="hidden lg:flex flex-col border-r border-gray-200 bg-gray-50 min-h-0">
                <div class="p-3 border-b border-gray-200">
                    <button type="button" @click="startNewChat()" :disabled="!rulesReady"
                            class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                        <span>+</span> New Chat
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-2 py-2 space-y-1">
                    <template x-for="s in history" :key="s.session_id">
                        <button type="button" @click="switchSession(s.session_id)"
                                class="w-full text-left px-2.5 py-2 rounded-lg text-xs truncate"
                                :class="s.session_id === sessionId ? 'bg-blue-100 text-blue-800 font-medium' : 'text-gray-600 hover:bg-gray-100'"
                                x-text="s.title"></button>
                    </template>
                    <p x-show="!history.length" class="px-2.5 py-2 text-xs text-gray-400">No conversations yet.</p>
                </div>
            </div>

            {{-- Chat pane --}}
            <div class="flex flex-col border-r border-gray-200 min-h-0">
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4" x-ref="scrollArea">
                    <template x-for="(msg, i) in messages" :key="i">
                        <div class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                            <div class="max-w-[85%] rounded-xl px-4 py-2.5 text-sm"
                                 :class="msg.role === 'user' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800'"
                                 x-text="msg.content"></div>
                        </div>
                    </template>
                    <div x-show="thinking" class="flex justify-start">
                        <div class="bg-gray-100 rounded-xl px-4 py-3 flex items-center gap-1">
                            <span class="typing-dot"></span>
                            <span class="typing-dot"></span>
                            <span class="typing-dot"></span>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-200 p-4">
                    <form @submit.prevent="send()" class="flex items-end gap-2">
                        <textarea x-model="draftInput" rows="2"
                                  :placeholder="rulesReady ? 'Describe your project...' : 'Upload your company rules on the right to get started...'"
                                  :disabled="!rulesReady"
                                  @keydown.enter.prevent="send()"
                                  class="flex-1 text-sm rounded-lg border-gray-300 focus:border-blue-400 focus:ring-blue-400 disabled:bg-gray-50 disabled:text-gray-400"></textarea>
                        <button type="submit" :disabled="thinking || !draftInput.trim() || !rulesReady"
                                class="px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            Send
                        </button>
                    </form>
                </div>
            </div>

            {{-- Right panel: Company Rules / Live Project Draft tabs --}}
            <div class="flex flex-col min-h-0 bg-gray-50">
                <div class="flex border-b border-gray-200 bg-white">
                    <button type="button" @click="activeTab = 'rules'"
                            class="flex-1 px-4 py-3 text-xs font-semibold uppercase tracking-wide border-b-2 transition"
                            :class="activeTab === 'rules' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-400 hover:text-gray-600'">
                        Company Rules
                    </button>
                    <button type="button" @click="activeTab = 'draft'"
                            class="flex-1 px-4 py-3 text-xs font-semibold uppercase tracking-wide border-b-2 transition"
                            :class="activeTab === 'draft' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-400 hover:text-gray-600'">
                        Project Draft
                    </button>
                </div>

                {{-- Company Rules tab --}}
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3" x-show="activeTab === 'rules'">
                    @foreach ($categories as $prefix => $category)
                        <div class="bg-white border border-gray-200 rounded-xl p-3">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-lg bg-{{ $category['color'] }}-50 flex items-center justify-center shrink-0">
                                    <x-dashboard-icon name="doc" class="h-4 w-4 text-{{ $category['color'] }}-600" />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-xs font-semibold text-gray-900 truncate">{{ $category['label'] }}</h3>
                                    <p class="text-[11px] text-gray-400">
                                        {{ $prefix }} &middot; <span x-text="categories['{{ $prefix }}'].count"></span> <span x-text="categories['{{ $prefix }}'].count === 1 ? 'rule' : 'rules'"></span>
                                    </p>
                                </div>
                            </div>

                            <input type="file" id="knowledge-file-{{ $prefix }}" class="hidden"
                                   accept=".pdf,.doc,.docx,.txt,.md"
                                   @change="if ($event.target.files.length) startUpload('{{ $prefix }}', $event.target.files[0])">

                            <div class="mt-2"
                                 x-show="uploadCards['{{ $prefix }}'].phase === 'idle'"
                                 @dragover.prevent="uploadCards['{{ $prefix }}'].dragging = true"
                                 @dragleave.prevent="uploadCards['{{ $prefix }}'].dragging = false"
                                 @drop.prevent="uploadCards['{{ $prefix }}'].dragging = false; if ($event.dataTransfer.files.length) startUpload('{{ $prefix }}', $event.dataTransfer.files[0])"
                                 @click="document.getElementById('knowledge-file-{{ $prefix }}').click()"
                                 :class="uploadCards['{{ $prefix }}'].dragging ? 'border-blue-400 bg-blue-50' : 'border-gray-200'"
                                 class="border-2 border-dashed rounded-lg py-4 text-center transition cursor-pointer">
                                <p class="text-[11px] font-medium text-gray-600">Drag &amp; drop or <span class="text-blue-600 font-semibold">browse</span></p>
                                <p class="mt-0.5 text-[10px] text-gray-400">PDF, DOCX, TXT, MD</p>
                            </div>

                            <div class="mt-2" x-show="uploadCards['{{ $prefix }}'].phase !== 'idle'">
                                <p class="text-[11px] font-medium text-gray-900 truncate" x-text="uploadCards['{{ $prefix }}'].fileName"></p>

                                <div class="mt-1.5 h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-300"
                                         :class="uploadCards['{{ $prefix }}'].phase === 'error' ? 'bg-red-500' : (uploadCards['{{ $prefix }}'].phase === 'done' ? 'bg-green-500' : 'bg-blue-600')"
                                         :style="`width: ${uploadCards['{{ $prefix }}'].progress}%`"></div>
                                </div>

                                <p class="mt-1.5 text-[10px]" :class="uploadCards['{{ $prefix }}'].phase === 'error' ? 'text-red-600' : 'text-gray-500'" x-text="uploadCards['{{ $prefix }}'].statusText"></p>

                                <button x-show="uploadCards['{{ $prefix }}'].phase === 'done' || uploadCards['{{ $prefix }}'].phase === 'error'" type="button"
                                        @click="resetUploadCard('{{ $prefix }}')" class="mt-1.5 text-[10px] font-medium text-blue-600 hover:underline">
                                    Upload another file
                                </button>
                            </div>
                        </div>
                    @endforeach

                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                        <div class="px-3 py-2.5 border-b border-gray-100">
                            <h2 class="text-xs font-semibold text-gray-900">Uploaded Documents</h2>
                        </div>

                        <template x-for="doc in documentsList" :key="doc.id">
                            <div class="px-3 py-2.5 border-b border-gray-50 last:border-0 flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-gray-800 truncate" x-text="doc.original_filename"></p>
                                    <p class="text-[10px] text-gray-400" x-text="(categories[doc.category] ? categories[doc.category].label : doc.category) + ' · ' + doc.date + ' · ' + doc.extracted_sections + ' sections'"></p>
                                </div>
                                <button type="button" @click="deleteDocument(doc.id)" title="Delete document"
                                        class="shrink-0 text-gray-300 hover:text-red-600 transition text-sm leading-none">
                                    &times;
                                </button>
                            </div>
                        </template>
                        <p x-show="!documentsList.length" class="px-3 py-6 text-center text-xs text-gray-400">No documents uploaded yet.</p>
                    </div>
                </div>

                {{-- Live draft tab --}}
                <div class="flex-1 overflow-y-auto px-6 py-4" x-show="activeTab === 'draft'">
                    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Live Project Draft</h2>

                    <div class="mt-3 space-y-3">
                        <div class="bg-white rounded-lg border border-gray-200 p-3">
                            <div class="text-xs text-gray-400">Project Name</div>
                            <div class="text-sm font-medium text-gray-900" x-text="draft.name || '—'"></div>
                        </div>

                        <div class="bg-white rounded-lg border border-gray-200 p-3">
                            <div class="text-xs text-gray-400">Project Type</div>
                            <div class="text-sm font-medium text-gray-900" x-text="draft.primary_project_type || 'Not yet determined'"></div>
                            <div class="text-xs mt-1" :class="draft.classification_status === 'confirmed' ? 'text-green-600' : 'text-amber-600'"
                                 x-text="draft.classification_status === 'confirmed' ? 'Confirmed' : (draft.primary_project_type ? 'Needs confirmation' : '')"></div>
                        </div>

                        <div class="bg-white rounded-lg border border-gray-200 p-3">
                            <div class="text-xs text-gray-400">Business Objective</div>
                            <div class="text-sm text-gray-800" x-text="draft.business_objective || 'Not yet provided'"></div>
                        </div>

                        <div class="bg-white rounded-lg border border-gray-200 p-3">
                            <div class="text-xs text-gray-400">Start Date</div>
                            <div class="text-sm text-gray-800" x-text="draft.start_date || 'Not yet provided'"></div>
                        </div>

                        <div class="bg-white rounded-lg border border-gray-200 p-3" x-show="(draft.roles || []).length || draft.has_authentication">
                            <div class="text-xs text-gray-400">Characteristics</div>
                            <div class="text-sm text-gray-800">
                                <span x-show="draft.has_authentication">Authentication required. </span>
                                <span x-show="(draft.roles || []).length" x-text="'Roles: ' + (draft.roles || []).join(', ')"></span>
                            </div>
                        </div>

                        <div x-show="clarifications.length" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <div class="text-xs font-semibold text-amber-700">Clarifications Needed</div>
                            <ul class="mt-1 space-y-1 text-sm text-amber-800 list-disc list-inside">
                                <template x-for="(c, i) in clarifications" :key="i">
                                    <li x-text="c.question"></li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    <button type="button" @click="confirm()" :disabled="analysisStatus !== 'ready' || confirming"
                            class="mt-6 w-full inline-flex justify-center items-center px-4 py-2.5 text-white text-sm font-medium rounded-lg
                                   bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed">
                        <span x-text="confirming ? 'Creating...' : 'Review & Create Project'"></span>
                    </button>
                    <p class="mt-2 text-xs text-gray-400" x-show="analysisStatus !== 'ready'">
                        Keep chatting until every required item above is resolved.
                    </p>
                    <p class="mt-2 text-xs text-red-600" x-show="confirmError" x-text="confirmError"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function projectCreationChat() {
            const categoryPrefixes = @json(array_keys($categories));

            const uploadCards = {};
            categoryPrefixes.forEach((prefix) => {
                uploadCards[prefix] = { dragging: false, phase: 'idle', progress: 0, fileName: '', statusText: '' };
            });

            const initialCategories = @json($categories);
            const initialDocuments = @json($documents);
            const initialRulesReady = @json(collect($categories)->sum('count') > 0);

            return {
                sessionId: null,
                messages: [],
                draft: {},
                clarifications: [],
                analysisStatus: 'gathering',
                draftInput: '',
                thinking: false,
                confirming: false,
                confirmError: null,
                history: [],

                activeTab: initialRulesReady ? 'draft' : 'rules',
                rulesReady: initialRulesReady,
                categories: initialCategories,
                documentsList: initialDocuments,
                uploadCards,

                init() {
                    this.trySessionStart();
                },

                // Returns a Promise<boolean> -- true once a session is
                // actually usable (company rules exist). Reused by init()
                // and by the upload success handler so uploading the first
                // rule document flips straight into the chat without a page
                // reload.
                trySessionStart() {
                    return fetch('/projects/new/session', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    })
                        .then(async r => {
                            const data = await r.json();

                            if (!r.ok) {
                                this.rulesReady = false;
                                if (!this.messages.length) {
                                    this.messages = [{
                                        role: 'assistant',
                                        content: data.message || "Upload your company's Business Rules on the right to get started -- I'll switch you into the chat automatically once they're ready.",
                                    }];
                                }
                                return false;
                            }

                            this.rulesReady = true;
                            this.applySession(data.session);
                            this.loadHistory();
                            return true;
                        });
                },

                loadHistory() {
                    fetch('/projects/new/sessions', {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then(r => r.json())
                        .then(data => { this.history = data.sessions || []; });
                },

                switchSession(id) {
                    if (id === this.sessionId || this.thinking) return;

                    fetch(`/projects/new/sessions/${id}`, {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then(r => r.json())
                        .then(data => this.applySession(data));
                },

                startNewChat() {
                    if (this.thinking || !this.rulesReady) return;

                    fetch('/projects/new/session?new=1', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    })
                        .then(r => r.json())
                        .then(data => {
                            this.applySession(data.session);
                            this.loadHistory();
                        });
                },

                applySession(session) {
                    this.sessionId = session.session_id;
                    this.messages = session.messages || [];
                    this.draft = session.draft || {};
                    this.clarifications = session.clarifications || [];
                    this.analysisStatus = session.analysis_status || 'gathering';
                    this.$nextTick(() => this.scrollToBottom());
                },

                send() {
                    const text = this.draftInput.trim();
                    if (!text || this.thinking || !this.sessionId || !this.rulesReady) return;

                    this.messages.push({ role: 'user', content: text });
                    this.draftInput = '';
                    this.thinking = true;
                    this.$nextTick(() => this.scrollToBottom());

                    fetch(`/projects/new/sessions/${this.sessionId}/messages`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ message: text }),
                    })
                        .then(r => r.json())
                        .then(data => {
                            this.messages.push({ role: 'assistant', content: data.assistant_message });
                            this.draft = data.draft || {};
                            this.clarifications = data.clarifications || [];
                            this.analysisStatus = data.analysis_status || 'gathering';
                            this.thinking = false;
                            this.$nextTick(() => this.scrollToBottom());
                            this.loadHistory();
                        })
                        .catch(() => { this.thinking = false; });
                },

                confirm() {
                    if (this.confirming || !this.sessionId) return;
                    this.confirming = true;
                    this.confirmError = null;

                    fetch(`/projects/new/sessions/${this.sessionId}/confirm`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    })
                        .then(async r => {
                            const data = await r.json();
                            if (!r.ok) {
                                this.confirmError = data.message || 'Could not create the project.';
                                this.confirming = false;
                                return;
                            }
                            window.location.href = data.redirect;
                        })
                        .catch(() => { this.confirming = false; this.confirmError = 'Something went wrong.'; });
                },

                scrollToBottom() {
                    const el = this.$refs.scrollArea;
                    if (el) el.scrollTop = el.scrollHeight;
                },

                // -- Company Rules tab: upload/delete --------------------

                startUpload(prefix, file) {
                    const card = this.uploadCards[prefix];
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
                    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());

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

                                this.categories[prefix].count += data.total_rules;
                                this.documentsList.unshift({
                                    id: data.document_id,
                                    original_filename: data.filename,
                                    category: data.category,
                                    date: 'Just now',
                                    status: 'indexed',
                                    extracted_sections: data.total_rules,
                                });

                                this.trySessionStart().then(ready => {
                                    if (ready) this.activeTab = 'draft';
                                });
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

                resetUploadCard(prefix) {
                    const card = this.uploadCards[prefix];
                    card.phase = 'idle';
                    card.progress = 0;
                    card.fileName = '';
                    card.statusText = '';
                    document.getElementById('knowledge-file-' + prefix).value = '';
                },

                deleteDocument(id) {
                    if (!confirm('Delete this document and its extracted rules? This cannot be undone.')) return;

                    const doc = this.documentsList.find(d => d.id === id);

                    fetch(`/knowledge-base/documents/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status !== 'deleted') return;

                            this.documentsList = this.documentsList.filter(d => d.id !== id);

                            if (doc && this.categories[doc.category]) {
                                this.categories[doc.category].count = Math.max(0, this.categories[doc.category].count - doc.extracted_sections);
                            }
                        });
                },
            }
        }
    </script>
</x-dashboard-layout>
