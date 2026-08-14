<x-guest-layout>
    <div class="mb-6 text-center">
        <span class="text-lg font-bold tracking-tight text-gray-900">ProjectFlow AI</span>
        <h1 class="mt-3 text-xl font-semibold text-gray-900">Set Up Your Company</h1>
        <p class="mt-1 text-sm text-gray-500">
            Every knowledge base, document, and project in ProjectFlow AI belongs to a company.
            Create yours to get started.
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('company.store') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="name" value="Company Name" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                          :value="old('name')" required autofocus placeholder="Acme Corporation" />
        </div>

        <div>
            <x-input-label for="description" value="Description (optional)" />
            <textarea id="description" name="description" rows="3"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-400 focus:ring-blue-400 text-sm"
                      placeholder="What does your company do?">{{ old('description') }}</textarea>
        </div>

        <button type="submit"
                class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
            Create Company &amp; Continue
        </button>
    </form>
</x-guest-layout>
