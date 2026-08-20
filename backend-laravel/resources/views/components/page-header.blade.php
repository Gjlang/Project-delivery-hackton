@props(['title', 'subtitle' => null])

<div class="flex items-center justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="shrink-0 flex items-center gap-3">
            {{ $actions }}
        </div>
    @endisset
</div>
