@props(['name'])

@php
    $paths = [
        'grid' => 'M4 5h6v6H4V5Zm10 0h6v6h-6V5ZM4 15h6v6H4v-6Zm10 0h6v6h-6v-6Z',
        'book' => 'M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5v-15Z M4 20.5A2.5 2.5 0 0 1 6.5 18H20',
        'users' => 'M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2 M11 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M23 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
        'plus' => 'M12 5v14M5 12h14',
        'spark' => 'M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M18.4 5.6l-2.8 2.8M8.4 15.6l-2.8 2.8',
        'doc' => 'M14 3v5h5 M6 3h8l6 6v11a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z M9 13h6 M9 17h6',
        'warning' => 'M12 9v4m0 4h.01M10.29 3.86 1.82 18a1 1 0 0 0 .87 1.5h18.62a1 1 0 0 0 .87-1.5L13.71 3.86a1 1 0 0 0-1.72 0Z',
        'logout' => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4 M16 17l5-5-5-5 M21 12H9',
        'upload' => 'M12 16V4m0 0 4 4m-4-4-4 4 M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3',
        'x' => 'M18 6 6 18M6 6l12 12',
        'refresh' => 'M21 12a9 9 0 1 1-2.64-6.36M21 4v6h-6',
    ];
@endphp

<svg {{ $attributes }} fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $paths[$name] ?? '' }}" />
</svg>
