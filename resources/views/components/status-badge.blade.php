@props(['status'])

@php
    $map = [
        'SELESAI' => 'ui-badge-success',
        'BATAL' => 'ui-badge-danger',
    ];
    $classes = $map[$status] ?? 'ui-badge-workflow';
@endphp

<span {{ $attributes->merge(['class' => "ui-badge {$classes}"]) }}>
    {{ $status }}
</span>
