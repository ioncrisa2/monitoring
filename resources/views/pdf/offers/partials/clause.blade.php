<article class="clause" data-clause-key="{{ $clause['key'] }}">
    <table class="clause-block" role="presentation">
        <colgroup>
            <col width="5%" style="width: 5%">
            <col width="30%" style="width: 30%">
            <col width="3%" style="width: 3%">
            <col width="62%" style="width: 62%">
        </colgroup>
        <tbody>
            <tr class="clause-row">
                <td width="5%" class="clause-number">{{ $clause['number'] }}.</td>
                <td width="30%" class="clause-title">{{ $clause['title'] }}</td>
                <td width="3%" class="clause-colon">:</td>
                <td width="62%" class="clause-content">
                    @foreach ($clause['blocks'] as $block)
                        <div class="clause-content-block block-{{ $block['type'] }}">
                        @include('pdf.offers.partials.block', ['block' => $block])
                        </div>
                    @endforeach
                </td>
            </tr>
        </tbody>
    </table>
</article>
