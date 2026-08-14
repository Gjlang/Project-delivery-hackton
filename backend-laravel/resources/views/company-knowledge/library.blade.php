<x-dashboard-layout title="Knowledge Base Library">
    <div class="p-8" x-data="knowledgeManager({{ $project->id }})">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $project->name }} &middot; Knowledge Base Library</h1>
                <p class="mt-1 text-sm text-gray-500">Every document uploaded to this project's Company Knowledge, with full edit and delete control.</p>
            </div>
            <a href="{{ route('projects.company-knowledge.index', $project) }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">
                &larr; Back to Company Knowledge
            </a>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('projects.company-knowledge.library', $project) }}" class="mt-6 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-500">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by file name or title..."
                       class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">Category</label>
                <select name="category" class="mt-1 text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
                    <option value="">All Categories</option>
                    @foreach ($categories as $key => $cat)
                        <option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $cat['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">Status</label>
                <select name="status" class="mt-1 text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
                    <option value="">All Statuses</option>
                    @foreach (['indexed' => 'Indexed', 'processing' => 'Processing', 'error' => 'Error'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Filter
            </button>
            @if (($filters['search'] ?? '') || ($filters['category'] ?? '') || ($filters['status'] ?? ''))
                <a href="{{ route('projects.company-knowledge.library', $project) }}" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">
                    Clear
                </a>
            @endif
        </form>

        {{-- Table --}}
        <div class="mt-6 bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                        <th class="px-5 py-3 font-medium">Doc Name</th>
                        <th class="px-5 py-3 font-medium">Category</th>
                        <th class="px-5 py-3 font-medium">Version</th>
                        <th class="px-5 py-3 font-medium">Date</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Extracted</th>
                        <th class="px-5 py-3 font-medium text-right">Actions</th>
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
                            <td class="px-5 py-3" @click.stop>
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
                                    <form method="POST" action="{{ route('projects.company-knowledge.documents.destroy', [$project, $document]) }}"
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
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">No documents match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $documents->links() }}
        </div>

        @include('company-knowledge._details-panel')
        @include('company-knowledge._edit-modal')
    </div>
</x-dashboard-layout>
