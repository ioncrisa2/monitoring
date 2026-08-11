<div class="recipient">
    <p>Yth.</p>
    <p class="recipient-name">{{ $snapshot['recipient']['name'] }}</p>

    @if ($snapshot['recipient']['attention'])
        <p>u.p. {{ $snapshot['recipient']['attention'] }}</p>
    @endif

    @foreach ($snapshot['recipient']['address_lines'] as $line)
        <p>{{ $line }}</p>
    @endforeach
</div>
