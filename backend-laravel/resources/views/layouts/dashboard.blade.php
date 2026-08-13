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
                    <span class="block text-xs text-gray-400">Enterprise Planner</span>
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

            <nav class="flex-1 px-3 py-4 space-y-1">
                @php
                    $navItems = [
                        ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid'],
                        ['route' => 'company-knowledge.index', 'label' => 'Company Knowledge', 'icon' => 'book'],
                        ['route' => 'employees.index', 'label' => 'Employees', 'icon' => 'users'],
                        ['route' => 'projects.new', 'label' => 'New Project', 'icon' => 'plus'],
                        ['route' => 'ai-analysis.index', 'label' => 'AI Analysis', 'icon' => 'spark'],
                        ['route' => 'project-plan.index', 'label' => 'Project Plan', 'icon' => 'doc'],
                        ['route' => 'risks.index', 'label' => 'Risks', 'icon' => 'warning'],
                    ];
                @endphp

                @foreach ($navItems as $item)
                    @php $active = request()->routeIs($item['route']); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition
                              {{ $active ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        <x-dashboard-icon :name="$item['icon']" class="h-5 w-5 {{ $active ? 'text-blue-600' : 'text-gray-400' }}" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
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
</body>
</html>
