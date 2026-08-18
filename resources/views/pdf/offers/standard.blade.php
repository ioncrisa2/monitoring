<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $snapshot['document']['subject'] }} - {{ $snapshot['document']['number'] }}</title>
    <style>{!! $printCss !!}</style>
</head>
<body>
    @if($showDraftWatermark)
        <div class="draft-watermark">DRAF — BELUM DISETUJUI</div>
    @endif

    <main>
        @include('pdf.offers.partials.letter-meta')
        @include('pdf.offers.partials.recipient')

        <p class="letter-subject">{{ $snapshot['document']['subject'] }}</p>
        <p class="body-copy">{{ $snapshot['document']['opening'] }}</p>

        <section class="clauses" aria-label="Ketentuan penugasan">
            @foreach ($snapshot['clauses'] as $clause)
                @include('pdf.offers.partials.clause', ['clause' => $clause])
            @endforeach
        </section>

        <p class="body-copy closing-copy">{{ $snapshot['document']['closing'] }}</p>

        @include('pdf.offers.partials.signatures')
    </main>
</body>
</html>
