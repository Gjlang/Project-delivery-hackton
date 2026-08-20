@props(['icon' => 'doc', 'title', 'description' => null])

<div class="py-14 text-center">
    <div class="mx-auto h-12 w-12 rounded-full bg-gray-50 flex items-center justify-center">
        <x-dashboard-icon :name="$icon" class="h-5 w-5 text-gray-400" />
    </div>
    <p class="mt-4 text-sm font-medium text-gray-700">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 text-sm text-gray-400 max-w-sm mx-auto">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-4">
            {{ $action }}
        </div>
    @endisset
</div>
