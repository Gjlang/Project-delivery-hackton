<x-dashboard-layout title="New Project">
    <div class="p-8 max-w-lg">
        <a href="{{ route('projects.index') }}" class="text-xs font-medium text-gray-400 hover:text-gray-600">
            &larr; All Projects
        </a>

        <h1 class="mt-2 text-2xl font-bold text-gray-900">New Project</h1>
        <p class="mt-1 text-sm text-gray-500">
            This information feeds the Requirement Analysis Agent, then you'll upload the Project Documents it should be built from.
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
                <x-input-label for="business_objective" value="Business Objective (recommended)" />
                <textarea id="business_objective" name="business_objective" rows="2"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-400 focus:ring-blue-400 text-sm"
                          placeholder="Why is this project needed? What outcome do you expect?">{{ old('business_objective') }}</textarea>
            </div>

            <div>
                <x-input-label for="description" value="Project Description (recommended)" />
                <textarea id="description" name="description" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-400 focus:ring-blue-400 text-sm"
                          placeholder="What is this project about?">{{ old('description') }}</textarea>
            </div>

            <div>
                <x-input-label for="requirements_raw" value="Requirements / Features (optional, one per line)" />
                <textarea id="requirements_raw" name="requirements_raw" rows="5"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-400 focus:ring-blue-400 text-sm font-mono"
                          placeholder="- Login&#10;- Employee CRUD&#10;- Leave request&#10;- Email notification">{{ old('requirements_raw') }}</textarea>
            </div>

            <div>
                <x-input-label for="start_date" value="Start Date (recommended)" />
                <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                              :value="old('start_date')" />
            </div>

            <button type="submit"
                    class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Create Project &amp; Continue to Project Documents
            </button>
        </form>
    </div>
</x-dashboard-layout>
