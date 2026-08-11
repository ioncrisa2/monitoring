@props([
    'type' => 'success',
    'dismissible' => true,
])

@php
    $meta = [
        'success' => [
            'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-200',
            'role' => 'status',
            'live' => 'polite',
        ],
        'error' => [
            'class' => 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/70 dark:bg-rose-950/40 dark:text-rose-200',
            'role' => 'alert',
            'live' => 'assertive',
        ],
        'warning' => [
            'class' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-200',
            'role' => 'status',
            'live' => 'polite',
        ],
        'info' => [
            'class' => 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900/70 dark:bg-blue-950/40 dark:text-blue-200',
            'role' => 'status',
            'live' => 'polite',
        ],
    ][$type] ?? null;

    $meta ??= [
        'class' => 'border-line bg-surface-subtle text-ink-secondary',
        'role' => 'status',
        'live' => 'polite',
    ];
@endphp

<div
    x-data="{ visible: true }"
    x-show="visible"
    role="{{ $meta['role'] }}"
    aria-live="{{ $meta['live'] }}"
    {{ $attributes->merge(['class' => "flex items-start justify-between gap-3 rounded-ui border px-4 py-3 text-sm leading-6 {$meta['class']}"]) }}
>
    <div>{{ $slot }}</div>

    @if($dismissible)
        <button type="button" x-on:click="visible = false" class="ui-icon-btn -my-1.5 -mr-2 h-9 w-9 shrink-0" aria-label="Tutup notifikasi">&times;</button>
    @endif
</div>
