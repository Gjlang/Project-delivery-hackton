<x-dashboard-layout title="Employees">
    <div class="p-8">
        <x-page-header title="Employees" subtitle="Your roster of employees, used by the AI to recommend a match for new projects." />

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

        {{-- Add Employee --}}
        <h2 class="mt-6 text-sm font-semibold text-gray-700">Add Employee</h2>
        <form method="POST" action="{{ route('employees.store') }}"
              class="mt-2 bg-white border border-gray-200 rounded-xl p-5 flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-500">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
            </div>
            <div class="min-w-[180px]">
                <label class="block text-xs font-medium text-gray-500">Role</label>
                <select name="role" required class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
                    <option value="">Select a role...</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[140px]">
                <label class="block text-xs font-medium text-gray-500">Skill Level</label>
                <select name="skill_level" required class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
                    @foreach (['Junior', 'Intermediate', 'Senior'] as $level)
                        <option value="{{ $level }}" @selected(old('skill_level') === $level)>{{ $level }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-500">Skills (comma-separated)</label>
                <input type="text" name="skills" value="{{ old('skills') }}" placeholder="PHP, Laravel, MySQL"
                       class="mt-1 w-full text-sm rounded-md border-gray-300 focus:border-blue-400 focus:ring-blue-400">
            </div>
            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Add Employee
            </button>
        </form>

        {{-- Table --}}
        <h2 class="mt-8 text-sm font-semibold text-gray-700">Roster</h2>
        <div class="mt-2 bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                        <th class="px-5 py-3 font-medium">Name</th>
                        <th class="px-5 py-3 font-medium">Role</th>
                        <th class="px-5 py-3 font-medium">Skill Level</th>
                        <th class="px-5 py-3 font-medium">Skills</th>
                        <th class="px-5 py-3 font-medium">Active Projects</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50 transition">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-7 w-7 rounded-full bg-blue-50 text-blue-600 text-xs font-semibold flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($employee->name, 0, 1)) }}
                                    </div>
                                    <span class="font-medium text-gray-900">{{ $employee->name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $employee->role }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600">{{ $employee->skill_level }}</span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">
                                @foreach (($employee->skills ?? []) as $skill)
                                    <span class="inline-block px-2 py-0.5 mr-1 mb-1 text-xs rounded-full bg-gray-100 text-gray-600">{{ $skill }}</span>
                                @endforeach
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $employee->active_project_count }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$employee->status" /></td>
                            <td class="px-5 py-3 text-right">
                                <form method="POST" action="{{ route('employees.destroy', $employee) }}"
                                      onsubmit="return confirm('Remove &quot;{{ addslashes($employee->name) }}&quot;? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">
                                        Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state icon="users" title="No employees yet" description="Add your first one above -- the AI uses this roster to recommend a match for new projects." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-dashboard-layout>
