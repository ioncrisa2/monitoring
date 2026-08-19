@if($block['type'] === 'text' || ($block['type'] === 'dynamic' && isset($block['text'])))
    <p class="clause-text">{{ $block['text'] }}</p>
@elseif($block['type'] === 'bullets' || ($block['type'] === 'dynamic' && isset($block['items'])))
    <ul class="clause-list">
        @foreach ($block['items'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
@elseif($block['type'] === 'asset_list')
    <table class="data-table asset-table">
        <thead><tr><th class="cell-number">No.</th><th>Subjek</th><th>Objek Penilaian</th><th>Lokasi / Dokumen</th></tr></thead>
        <tbody>
            @foreach ($block['rows'] as $row)
                <tr>
                    <td class="cell-number">{{ $row['number'] }}</td>
                    <td>{{ $row['subject'] }}</td>
                    <td>{{ $row['asset'] }}</td>
                    <td>
                        <p>{{ $row['location'] }}</p>
                        @if($row['documents'] !== '')<p class="cell-secondary">{{ $row['documents'] }}</p>@endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@elseif($block['type'] === 'fee_summary')
    <table class="data-table summary-table"><tbody>
        @foreach ($block['rows'] as $row)
            <tr><th>{{ $row['label'] }}</th><td class="cell-money">{{ $row['value'] }}</td></tr>
        @endforeach
    </tbody></table>
    @if($block['amount_in_words'])<p class="amount-in-words">Terbilang: {{ $block['amount_in_words'] }}.</p>@endif
@elseif($block['type'] === 'fee_table')
    <table class="data-table fee-table">
        <thead><tr><th class="cell-number">No.</th><th>Aset</th><th>Uraian</th><th class="cell-number">Qty</th><th class="cell-money">Harga Satuan</th><th class="cell-money">Jumlah</th></tr></thead>
        <tbody>
            @foreach ($block['rows'] as $row)
                <tr><td class="cell-number">{{ $row['number'] }}</td><td>{{ $row['asset'] }}</td><td>{{ $row['label'] }}</td><td class="cell-number">{{ $row['quantity'] }}</td><td class="cell-money">{{ $row['unit_amount'] }}</td><td class="cell-money">{{ $row['line_total'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
@elseif($block['type'] === 'payment_terms')
    <table class="data-table payment-table">
        <thead><tr><th class="cell-number">Termin</th><th class="cell-number">Porsi</th><th>Pemicu Pembayaran</th><th>Jatuh Tempo</th><th class="cell-money">Nilai</th></tr></thead>
        <tbody>
            @foreach ($block['rows'] as $row)
                <tr><td class="cell-number">{{ $row['number'] }}</td><td class="cell-number">{{ $row['percentage'] }}</td><td>{{ $row['trigger'] }}</td><td>{{ $row['due'] }}</td><td class="cell-money">{{ $row['amount'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
@elseif($block['type'] === 'requirements')
    <table class="data-table requirements-table">
        <thead><tr><th class="cell-number">No.</th><th>Kode</th><th>Dokumen / Data yang Diperlukan</th></tr></thead>
        <tbody>
            @foreach ($block['rows'] as $row)
                <tr><td class="cell-number">{{ $row['number'] }}</td><td>{{ $row['code'] }}</td><td class="emphasis-{{ $row['emphasis'] }}">{{ $row['description'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
@elseif($block['type'] === 'exposure_table')
    @if($block['rows'] === [])
        <p class="empty-table-message">{{ $block['empty_message'] }}</p>
    @else
        <table class="data-table exposure-table">
            <thead><tr><th class="cell-number">No.</th><th>Aset</th><th class="cell-money">Exposure</th><th class="cell-money">Nilai Pasar</th><th class="cell-money">Nilai Likuidasi</th><th class="cell-number">Diskon</th></tr></thead>
            <tbody>
                @foreach ($block['rows'] as $row)
                    <tr><td class="cell-number">{{ $row['number'] }}</td><td>{{ $row['asset'] }}</td><td class="cell-money">{{ $row['exposure'] }}</td><td class="cell-money">{{ $row['market_value'] }}</td><td class="cell-money">{{ $row['liquidation_value'] }}</td><td class="cell-number">{{ $row['discount'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif
