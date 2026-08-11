@props(['disabled' => false])

<textarea @disabled($disabled) {{ $attributes->merge(['class' => 'ui-field']) }}>{{ $slot }}</textarea>
