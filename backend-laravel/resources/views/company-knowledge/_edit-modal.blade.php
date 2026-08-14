{{-- Edit modal. Requires x-data="knowledgeManager()" on an ancestor and a $categories variable in scope. --}}
<div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display:none">
    <div class="absolute inset-0 bg-black/30" @click="closeEdit()"></div>

    <div x-show="editing" x-transition class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6" @click.stop>
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Edit Document</h3>
            <button @click="closeEdit()" class="text-gray-400 hover:text-gray-600">
                <x-dashboard-icon name="x" class="h-5 w-5" />
            </button>
        </div>

        <form @submit.prevent="submitEdit()" class="mt-4 space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-500">Title</label>
                <input type="text" x-model="editForm.title" required
                       class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500">Category</label>
                <select x-model="editForm.category" required
                        class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
                    @foreach ($categories as $key => $cat)
                        <option value="{{ $key }}">{{ $cat['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500">Version</label>
                <input type="text" x-model="editForm.version" required
                       class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500">Replace File (optional)</label>
                <input type="file" accept=".pdf,.doc,.docx,.txt,.md"
                       @change="editForm.file = $event.target.files[0]"
                       class="mt-1 w-full text-xs text-gray-600">
                <p class="mt-1 text-[11px] text-gray-400">Current: <span x-text="editForm.filename"></span></p>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="closeEdit()" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
