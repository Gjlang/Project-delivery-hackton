<x-dashboard-layout title="New Project">
    <div class="p-8 max-w-lg">
        <a href="{{ route('projects.index') }}" class="text-xs font-medium text-gray-400 hover:text-gray-600">
            &larr; All Projects
        </a>

        <h1 class="mt-2 text-2xl font-bold text-gray-900">New Project</h1>
        <p class="mt-1 text-sm text-gray-500">
            Start by naming the project. Next you'll upload the Company Knowledge documents it should be built from.
        </p>

        @if ($errors->any())
            <div class="mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('projects.store') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <x-input-label for="name" value="Project Name" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                              :value="old('name')" required autofocus placeholder="Core Banking Transformation" />
            </div>

            <div>
                <x-input-label for="description" value="Description (optional)" />
                <textarea id="description" name="description" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-400 focus:ring-blue-400 text-sm"
                          placeholder="What is this project about?">{{ old('description') }}</textarea>
            </div>

            <button type="submit"
                    class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Create Project &amp; Continue to Company Knowledge
            </button>
        </form>
    </div>
</x-dashboard-layout>
