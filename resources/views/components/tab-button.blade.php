@props(['active' => false])

<button
    type="button"
    role="tab"
    aria-selected="{{ $active ? 'true' : 'false' }}"
    {{ $attributes->class([
        'ui-tab',
        'ui-tab-active' => $active,
    ]) }}
>
    {{ $slot }}
</button>
