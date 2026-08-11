@props(['outcome'])

@php
    $meta = [
        'DRAFT' => ['label' => 'Draft', 'class' => 'ui-badge-neutral'],
        'DIKIRIM' => ['label' => 'Dikirim', 'class' => 'ui-badge-info'],
        'DITERIMA' => ['label' => 'Diterima', 'class' => 'ui-badge-success'],
        'TIDAK_LANJUT' => ['label' => 'Tidak lanjut', 'class' => 'ui-badge-warning'],
        'DITOLAK' => ['label' => 'Ditolak', 'class' => 'ui-badge-danger'],
    ][$outcome] ?? ['label' => $outcome, 'class' => 'ui-badge-neutral'];
@endphp

<span {{ $attributes->merge(['class' => "ui-badge {$meta['class']}"]) }}>
    {{ $meta['label'] }}
</span>
