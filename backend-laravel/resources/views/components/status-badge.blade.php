@props(['status', 'label' => null, 'color' => null])

@php
    $normalized = strtolower((string) $status);

    $resolvedColor = $color ?? match(true) {
        in_array($normalized, ['done', 'pass', 'active', 'indexed', 'success', 'confirmed', 'ready'], true) => 'green',
        in_array($normalized, ['in_progress', 'processing', 'draft', 'ready_to_confirm'], true) => 'blue',
        in_array($normalized, ['fail', 'failed', 'error'], true) => 'red',
        in_array($normalized, ['warning', 'needs_information', 'leave', 'not_testable'], true) => 'amber',
        default => 'gray',
    };

    $resolvedLabel = $label ?? \Illuminate\Support\Str::of((string) $status)->replace('_', ' ')->title();
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-xs font-medium text-'.$resolvedColor.'-600']) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-{{ $resolvedColor }}-500"></span>
    {{ $resolvedLabel }}
</span>
