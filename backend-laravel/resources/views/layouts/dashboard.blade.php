<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'ProjectFlow AI' }} &middot; ProjectFlow AI</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900">
    <div class="min-h-screen flex">
        {{-- Sidebar --}}
        <aside class="w-64 shrink-0 bg-white border-r border-gray-200 flex flex-col">
            <div class="px-5 py-5 border-b border-gray-100">
                <a href="{{ route('dashboard') }}" class="block">
                    <span class="text-lg font-bold tracking-tight text-gray-900">ProjectFlow AI</span>
                    <span class="block text-xs text-gray-400">{{ auth()->user()->company->name ?? 'Enterprise Planner' }}</span>
                </a>
            </div>

            <div class="px-4 pt-4">
                <div class="relative">
                    <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5A6.5 6.5 0 1 1 4 10.5a6.5 6.5 0 0 1 13 0Z" />
                    </svg>
                    <input type="text" placeholder="Search knowledge base..."
                           class="w-full text-sm rounded-md border-gray-200 bg-gray-50 pl-9 pr-3 py-2 focus:border-blue-400 focus:ring-blue-400" />
                </div>
            </div>

            <nav class="flex-1 px-3 py-4 space-y-4 overflow-y-auto">
                @php
                    $sections = [
                        'Company' => [
                            ['route' => 'projects.new', 'label' => 'Project Planner', 'icon' => 'spark'],
                            ['route' => 'rules.index', 'label' => 'Rules Management', 'icon' => 'shield'],
                            ['route' => 'employees.index', 'label' => 'Employees', 'icon' => 'users'],
                        ],
                    ];

                    // Reused both for the currently-active project's expanded
                    // sub-nav below, and previously for the standalone
                    // "Current Project" block this replaces.
                    $projectNav = [
                        ['route' => 'projects.company-knowledge.index', 'label' => 'Overview', 'icon' => 'grid'],
                        ['route' => 'projects.phases.index', 'label' => 'Phases', 'icon' => 'refresh'],
                        ['route' => 'projects.testing.create', 'label' => 'Website Testing', 'icon' => 'flask'],
                    ];

                    $sidebarProjects = \App\Models\Project::where('created_by', auth()->id())->latest()->take(6)->get();
                    $sidebarProjectsTotal = \App\Models\Project::where('created_by', auth()->id())->count();
                @endphp

                @foreach ($sections as $sectionLabel => $items)
                    <div>
                        <div class="px-3 pb-1 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">{{ $sectionLabel }}</div>
                        <div class="space-y-1">
                            @foreach ($items as $item)
                                @php
                                    $active = request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*');
                                @endphp
                                <a href="{{ route($item['route']) }}"
                                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition
                                          {{ $active ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <x-dashboard-icon :name="$item['icon']" class="h-5 w-5 {{ $active ? 'text-blue-600' : 'text-gray-400' }}" />
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- Your Projects: click straight into a project, no separate index page needed --}}
                @if ($sidebarProjects->isNotEmpty())
                    <div>
                        <div class="px-3 pb-1 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Your Projects</div>
                        <div class="space-y-0.5">
                            @foreach ($sidebarProjects as $sidebarProject)
                                @php $isActiveProject = isset($project) && $project->id === $sidebarProject->id; @endphp
                                <div>
                                    <a href="{{ route('projects.phases.index', $sidebarProject) }}"
                                       class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition
                                              {{ $isActiveProject ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                        <span class="h-1.5 w-1.5 rounded-full shrink-0 {{ $isActiveProject ? 'bg-blue-600' : 'bg-gray-300' }}"></span>
                                        <span class="truncate">{{ $sidebarProject->name }}</span>
                                    </a>

                                    @if ($isActiveProject)
                                        <div class="ml-4 mt-0.5 mb-1 space-y-0.5 border-l border-gray-100 pl-3">
                                            @foreach ($projectNav as $navItem)
                                                @php
                                                    $navActive = request()->routeIs($navItem['route']) || request()->routeIs($navItem['route'].'.*')
                                                        || ($navItem['route'] === 'projects.testing.create' && request()->routeIs('projects.testing.*'));
                                                @endphp
                                                <a href="{{ route($navItem['route'], $project) }}"
                                                   class="flex items-center gap-2 px-2 py-1.5 rounded-md text-xs font-medium transition
                                                          {{ $navActive ? 'text-blue-700' : 'text-gray-500 hover:text-gray-800' }}">
                                                    <x-dashboard-icon :name="$navItem['icon']" class="h-3.5 w-3.5 {{ $navActive ? 'text-blue-600' : 'text-gray-400' }}" />
                                                    {{ $navItem['label'] }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            @if ($sidebarProjectsTotal > $sidebarProjects->count())
                                <a href="{{ route('projects.index') }}" class="block px-3 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-700">
                                    View all {{ $sidebarProjectsTotal }} projects &rarr;
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </nav>

            <div class="px-3 py-4 border-t border-gray-100">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <a href="{{ route('logout') }}"
                       onclick="event.preventDefault(); this.closest('form').submit();"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-900">
                        <x-dashboard-icon name="logout" class="h-5 w-5 text-gray-400" />
                        Log Out
                    </a>
                </form>
            </div>
        </aside>

        {{-- Main content --}}
        <div class="flex-1 flex flex-col min-w-0">
            <header class="h-16 border-b border-gray-200 bg-white flex items-center justify-end gap-4 px-6">
                <button class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9" />
                    </svg>
                </button>
                <div class="h-8 w-8 rounded-full bg-blue-600 text-white text-sm font-semibold flex items-center justify-center">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
            </header>

            <main class="flex-1 overflow-y-auto">
                {{ $slot }}
            </main>
        </div>
    </div>

    <script>
        function csrfToken() {
            return document.querySelector('meta[name="csrf-token"]').content;
        }

        function knowledgeManager(projectId) {
            const base = `/projects/${projectId}/company-knowledge/documents`;

            return {
                // Details slide-over panel
                visible: false,
                doc: null,
                open(id) {
                    this.visible = true;
                    this.doc = null;
                    fetch(`${base}/${id}`)
                        .then(r => r.json())
                        .then(data => this.doc = data);
                },
                close() {
                    this.visible = false;
                },
                reindex() {
                    if (!this.doc) return;
                    fetch(`${base}/${this.doc.id}/reindex`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken() },
                    }).then(() => window.location.reload());
                },

                // Edit modal
                editing: false,
                editForm: { id: null, title: '', category: '', version: '', filename: '', file: null },
                startEdit(document) {
                    this.editForm = {
                        id: document.id,
                        title: document.title,
                        category: document.category,
                        version: document.version,
                        filename: document.filename,
                        file: null,
                    };
                    this.editing = true;
                },
                closeEdit() {
                    this.editing = false;
                },
                submitEdit() {
                    const fd = new FormData();
                    fd.append('_method', 'PUT');
                    fd.append('title', this.editForm.title);
                    fd.append('category', this.editForm.category);
                    fd.append('version', this.editForm.version);
                    if (this.editForm.file) {
                        fd.append('file', this.editForm.file);
                    }

                    fetch(`${base}/${this.editForm.id}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken() },
                        body: fd,
                    }).then(() => window.location.reload());
                },
            }
        }
    </script>
</body>
</html>
