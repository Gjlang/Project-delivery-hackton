<x-dashboard-layout title="Rules Management">
    <div class="p-8" x-data="{ activeTab: '{{ request('category', array_key_first($categories)) }}' }">
        <x-page-header title="Rules Management" subtitle="Add, edit, or remove individual rules by hand -- no PDF upload required." />

        @if (session('status'))
            <div class="mt-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Add Rule --}}
        <form method="POST" action="{{ route('rules.store') }}"
              class="mt-6 bg-white border border-gray-200 rounded-xl p-5 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-[180px]">
                <label class="block text-xs font-medium text-gray-500">Category</label>
                <select name="category" required class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
                    @foreach ($categories as $prefix => $meta)
                        <option value="{{ $prefix }}" @selected(old('category') === $prefix)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[120px]">
                <label class="block text-xs font-medium text-gray-500">Rule Code</label>
                <input type="text" name="rule_code" value="{{ old('rule_code') }}" placeholder="BR-009" required
                       class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
            </div>
            <div class="min-w-[160px]">
                <label class="block text-xs font-medium text-gray-500">Section (optional)</label>
                <input type="text" name="section" value="{{ old('section') }}"
                       class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
            </div>
            <div class="flex-1 min-w-[180px]">
                <label class="block text-xs font-medium text-gray-500">Title</label>
                <input type="text" name="title" value="{{ old('title') }}" required
                       class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
            </div>
            <div class="w-full">
                <label class="block text-xs font-medium text-gray-500">Rule Text</label>
                <textarea name="rule_text" rows="2" required
                          class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">{{ old('rule_text') }}</textarea>
            </div>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Add Rule
            </button>
        </form>

        {{-- Category tabs --}}
        <div class="mt-6 flex flex-wrap gap-2 border-b border-gray-200">
            @foreach ($categories as $prefix => $meta)
                <button type="button" @click="activeTab = '{{ $prefix }}'"
                        :class="activeTab === '{{ $prefix }}' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="px-3 py-2 text-sm font-medium border-b-2 -mb-px transition">
                    {{ $meta['label'] }}
                    <span class="ml-1 text-xs text-gray-400">({{ $rulesByCategory[$prefix]->count() }})</span>
                </button>
            @endforeach
        </div>

        {{-- Rule tables --}}
        @foreach ($categories as $prefix => $meta)
            <div x-show="activeTab === '{{ $prefix }}'" x-cloak class="mt-4 bg-white border border-gray-200 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                            <th class="px-5 py-3 font-medium">Code</th>
                            <th class="px-5 py-3 font-medium">Title</th>
                            <th class="px-5 py-3 font-medium">Rule Text</th>
                            <th class="px-5 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rulesByCategory[$prefix] as $rule)
                            <tr class="border-b border-gray-50 last:border-0 align-top" x-data="{ editing: false }">
                                <td class="px-5 py-3 font-medium text-gray-900 whitespace-nowrap">{{ $rule->rule_code }}</td>
                                <td class="px-5 py-3 text-gray-700" colspan="2" x-show="!editing">
                                    <span class="font-medium">{{ $rule->title }}</span>
                                    <p class="text-gray-500 mt-1">{{ \Illuminate\Support\Str::limit($rule->rule_text, 160) }}</p>
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap" x-show="!editing">
                                    <button type="button" @click="editing = true" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                                    <form method="POST" action="{{ route('rules.destroy', [$prefix, $rule->id]) }}"
                                          class="inline"
                                          onsubmit="return confirm('Delete &quot;{{ addslashes($rule->rule_code) }}&quot;? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ml-3 text-red-500 hover:text-red-700 text-xs font-medium">Delete</button>
                                    </form>
                                </td>

                                <td colspan="3" class="px-5 py-3" x-show="editing" x-cloak>
                                    <form method="POST" action="{{ route('rules.update', [$prefix, $rule->id]) }}" class="flex flex-wrap items-end gap-3">
                                        @csrf
                                        @method('PUT')
                                        <div class="min-w-[160px]">
                                            <label class="block text-xs font-medium text-gray-500">Section</label>
                                            <input type="text" name="section" value="{{ $rule->section }}"
                                                   class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
                                        </div>
                                        <div class="flex-1 min-w-[160px]">
                                            <label class="block text-xs font-medium text-gray-500">Title</label>
                                            <input type="text" name="title" value="{{ $rule->title }}" required
                                                   class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
                                        </div>
                                        <div class="w-full">
                                            <label class="block text-xs font-medium text-gray-500">Rule Text</label>
                                            <textarea name="rule_text" rows="2" required
                                                      class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">{{ $rule->rule_text }}</textarea>
                                        </div>
                                        <button type="submit" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save</button>
                                        <button type="button" @click="editing = false" class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700">Cancel</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-gray-400">
                                    No {{ $meta['label'] }} rules yet. Add one above, or upload a document from the Knowledge Base.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</x-dashboard-layout>
