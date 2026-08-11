<table class="signatures" data-signing-mode="wet-ink" role="presentation">
    <tr>
        <td>
            <p>Hormat kami,</p>
            <p>{{ $snapshot['issuer']['name'] }}</p>
        </td>
        <td>
            <p>Menyetujui,</p>
            <p>{{ $snapshot['signatures']['client_name'] }}</p>
        </td>
    </tr>
    <tr class="signature-space">
        <td></td>
        <td></td>
    </tr>
    <tr>
        <td>
            <p class="signature-name">{{ $snapshot['signatures']['issuer_name'] }}</p>
            <p>{{ $snapshot['signatures']['issuer_title'] }}</p>
        </td>
        <td>
            <p class="signature-name">Nama jelas dan tanda tangan basah</p>

            @if ($snapshot['signatures']['client_title'])
                <p>{{ $snapshot['signatures']['client_title'] }}</p>
            @endif
        </td>
    </tr>
</table>
