@props(['status'])

@if ($status)
    <x-flash-message :dismissible="false" {{ $attributes }}>{{ $status }}</x-flash-message>
@endif
