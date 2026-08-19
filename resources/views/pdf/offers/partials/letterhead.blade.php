<header class="document-letterhead" aria-label="Kop surat penerbit">
    @if($snapshot['issuer']['letterhead']['verified'] && $snapshot['issuer']['letterhead']['uri'])
        <img class="letterhead-image" src="{{ $snapshot['issuer']['letterhead']['uri'] }}" alt="Kop surat {{ $snapshot['issuer']['name'] }}">
    @else
        <div class="letterhead-fallback">
            <p class="letterhead-name">{{ $snapshot['issuer']['name'] }}</p>
            @if($snapshot['issuer']['address_lines'] !== [])
                <p>{{ implode(' · ', $snapshot['issuer']['address_lines']) }}</p>
            @endif
            @if($snapshot['issuer']['contact_lines'] !== [])
                <p>{{ implode(' · ', $snapshot['issuer']['contact_lines']) }}</p>
            @endif
        </div>
    @endif
</header>
