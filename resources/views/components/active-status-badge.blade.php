@props(['active'])

<span {{ $attributes->merge(['class' => 'ui-badge ' . ($active ? 'ui-badge-success' : 'ui-badge-neutral')]) }}>
    {{ $active ? 'Aktif' : 'Nonaktif' }}
</span>
