@props(['title', 'description' => null])

<header {{ $attributes->merge(['class' => 'mb-6']) }}>
    <h1 class="text-xl font-semibold leading-7 tracking-tight text-ink">{{ $title }}</h1>
    @if($description)
        <p class="mt-1.5 text-sm leading-6 text-ink-secondary">{{ $description }}</p>
    @endif
</header>
