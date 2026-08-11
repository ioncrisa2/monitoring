<article class="clause" data-clause-key="{{ $clause['key'] }}">
    @foreach ($clause['paragraphs'] as $paragraph)
        <table class="clause-block" role="presentation">
            <tr>
                <td class="clause-number">{{ $loop->first ? $clause['number'].'.' : '' }}</td>
                <td class="clause-title">{{ $loop->first ? $clause['title'] : '' }}</td>
                <td class="clause-colon">{{ $loop->first ? ':' : '' }}</td>
                <td class="clause-content"><p>{{ $paragraph }}</p></td>
            </tr>
        </table>
    @endforeach

    @foreach ($clause['items'] as $item)
        <table class="clause-block" role="presentation">
            <tr>
                <td class="clause-number">{{ $clause['paragraphs'] === [] && $loop->first ? $clause['number'].'.' : '' }}</td>
                <td class="clause-title">{{ $clause['paragraphs'] === [] && $loop->first ? $clause['title'] : '' }}</td>
                <td class="clause-colon">{{ $clause['paragraphs'] === [] && $loop->first ? ':' : '' }}</td>
                <td class="clause-content clause-list-item"><span class="bullet">•</span><span>{{ $item }}</span></td>
            </tr>
        </table>
    @endforeach
</article>
