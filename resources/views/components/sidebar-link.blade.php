@props(['active' => false])

<a
    @if($active) aria-current="page" @endif
    {{ $attributes->class([
        'ui-sidebar-link',
        'ui-sidebar-link-active' => $active,
    ]) }}
>
    {{ $slot }}
</a>
