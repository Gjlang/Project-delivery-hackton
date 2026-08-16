<x-dashboard-layout title="Create Project with AI">
    <div class="h-full flex flex-col" x-data="projectCreationChat()" x-init="init()">
        <div class="px-8 py-5 border-b border-gray-200 bg-white flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Create Project with AI</h1>
                <p class="text-xs text-gray-500 mt-0.5">Describe your project naturally -- I'll check it against your company's rules as we go.</p>
            </div>
            <a href="{{ route('projects.new.legacy') }}" class="text-xs text-gray-400 hover:text-gray-600">Use the plain form instead &rarr;</a>
        </div>

        <template x-if="blocked">
            <div class="flex-1 flex items-center justify-center p-8">
                <div class="max-w-md text-center">
                    <p class="text-sm font-semibold text-red-700" x-text="blockedMessage"></p>
                    <a href="{{ route('company-rules.ui.index') }}" class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                        Set Up Company Rules
                    </a>
                </div>
            </div>
        </template>

        <div class="flex-1 grid grid-cols-1 lg:grid-cols-2 min-h-0" x-show="!blocked">
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
                    <div x-show="thinking" class="text-xs text-gray-400">Analyzing your project&hellip; checking company standards&hellip;</div>
                </div>

                <div class="border-t border-gray-200 p-4">
                    <form @submit.prevent="send()" class="flex items-end gap-2">
                        <textarea x-model="draftInput" rows="2" placeholder="Describe your project..."
                                  @keydown.enter.prevent="send()"
                                  class="flex-1 text-sm rounded-lg border-gray-300 focus:border-blue-400 focus:ring-blue-400"></textarea>
                        <button type="submit" :disabled="thinking || !draftInput.trim()"
                                class="px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            Send
                        </button>
                    </form>
                </div>
            </div>

            {{-- Live draft pane --}}
            <div class="overflow-y-auto px-6 py-4 bg-gray-50">
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

    <script>
        function projectCreationChat() {
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
                blocked: false,
                blockedMessage: '',

                init() {
                    fetch('/projects/new/session', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    })
                        .then(async r => {
                            const data = await r.json();
                            if (!r.ok) {
                                this.blocked = true;
                                this.blockedMessage = data.message || 'Company rules are required before creating a project.';
                                return;
                            }
                            this.applySession(data.session);
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
                    if (!text || this.thinking || !this.sessionId) return;

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
            }
        }
    </script>
</x-dashboard-layout>
