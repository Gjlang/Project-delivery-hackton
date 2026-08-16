@props(['project'])

<div x-data="{ open: false }" class="relative px-3 pb-2">
    <button @click="open = !open" type="button"
            class="w-full flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-left text-sm font-medium text-gray-800 hover:bg-gray-100">
        <span class="truncate">{{ $project->name }}</span>
        <x-dashboard-icon name="switch" class="h-4 w-4 shrink-0 text-gray-400" />
    </button>

    <div x-show="open" @click.outside="open = false" x-cloak
         class="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
        @foreach (\App\Models\Project::where('company_id', auth()->user()->company_id)->latest()->get() as $p)
            <a href="{{ route('projects.company-knowledge.index', $p) }}"
               class="block truncate px-3 py-2 text-sm {{ $p->id === $project->id ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                {{ $p->name }}
            </a>
        @endforeach
    </div>
</div>
