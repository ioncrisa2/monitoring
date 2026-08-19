<?php

namespace Tests\Feature;

use App\Enums\OfferDocumentOutputMode;
use App\Services\Offers\IndonesianAmountSpeller;
use App\Services\Offers\OfferDocumentRenderer;
use App\Services\Offers\OfferFeeCalculator;
use App\Services\Offers\OfferPreflightValidator;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

class OfferDocumentRendererV2Test extends TestCase
{
    private string $assetDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assetDirectory = storage_path('framework/testing/offer-document-assets-v2');
        File::ensureDirectoryExists($this->assetDirectory);
        config()->set('offer-documents.renderer.approved_asset_path', $this->assetDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->assetDirectory);

        parent::tearDown();
    }

    public function test_v2_renders_every_safe_block_as_escaped_content_and_real_tables(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['clauses'][0]['blocks'][0]['text'] = 'Aman <script>alert("x")</script>';

        $html = app(OfferDocumentRenderer::class)->renderHtml($snapshot);

        $this->assertStringContainsString('position: fixed', $html);
        $this->assertStringContainsString('DRAF — BELUM DISETUJUI', $html);
        $this->assertStringContainsString('Aman &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert("x")</script>', $html);
        $this->assertStringContainsString('class="data-table asset-table"', $html);
        $this->assertStringContainsString('class="data-table fee-table"', $html);
        $this->assertStringContainsString('class="data-table payment-table"', $html);
        $this->assertStringContainsString('class="data-table requirements-table"', $html);
        $this->assertStringContainsString('class="data-table exposure-table"', $html);
        $this->assertStringContainsString('display: table-header-group', $html);
        $this->assertStringNotContainsString('Halaman 1', $html);
        $this->assertStringNotContainsString('Penawaran 001/TEST', $html);
    }

    public function test_print_ready_v2_requires_and_renders_a_verified_local_letterhead(): void
    {
        $path = $this->assetDirectory.DIRECTORY_SEPARATOR.'kop.png';
        File::put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));
        $snapshot = $this->snapshot();
        $snapshot['issuer']['letterhead'] = [
            'configured' => true,
            'verified' => true,
            'path' => 'kop.png',
            'mime' => 'image/png',
            'sha256' => hash_file('sha256', $path),
        ];
        $snapshot['metadata']['issuer_profile']['letterhead_verified'] = true;

        $html = app(OfferDocumentRenderer::class)->renderHtml(
            $snapshot,
            OfferDocumentOutputMode::PrintReady,
        );

        $this->assertStringContainsString('class="letterhead-image"', $html);
        $this->assertStringContainsString('file:///', $html);
        $this->assertStringNotContainsString('class="draft-watermark"', $html);

        $pdf = app(OfferDocumentRenderer::class)->render($snapshot, OfferDocumentOutputMode::PrintReady);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringNotContainsString('/Type /Sig', $pdf);

        $snapshot['issuer']['letterhead']['sha256'] = str_repeat('0', 64);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('letterhead resmi');
        app(OfferDocumentRenderer::class)->renderHtml($snapshot, OfferDocumentOutputMode::PrintReady);
    }

    public function test_strict_auction_preflight_enforces_exposure_discount_and_one_fee_per_asset(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['engagement'] = [
            'recipient_organization' => 'PT Klien',
            'recipient_address' => 'Jl. Klien 1',
            'recipient_city' => 'Jakarta',
            'issue_city' => 'Jakarta',
            'subject' => 'Penawaran Lelang',
            'request_reference_type' => 'none',
            'ownership_form' => 'Hak Milik',
            'currency' => 'IDR',
            'purpose' => 'Pelaksanaan lelang',
            'valuation_basis' => 'Nilai Pasar dan Nilai Likuidasi',
            'valuation_date' => '2026-08-19',
            'valuation_date_rule' => null,
            'investigation_level' => 'full',
            'report_format' => 'complete',
            'report_language' => 'id',
            'report_copies' => 2,
            'completion_days' => 10,
            'completion_day_type' => 'business',
            'tax_inclusion' => 'excluded',
            'fee_presentation' => 'per_asset',
        ];
        $snapshot['subjects'] = [[
            'id' => 1,
            'name_snapshot' => 'PT Debitur',
            'is_primary' => true,
            'assets' => [[
                'id' => 11,
                'asset_type' => 'tanah_bangunan',
                'address' => 'Jl. Aset 1',
                'exposure_amount' => 700_000_000,
                'reference_market_value' => 1_000_000_000,
                'reference_liquidation_value' => 700_000_000,
                'liquidation_discount_bps' => 3000,
                'documents' => [[
                    'document_type' => 'SHM',
                    'document_no' => '001',
                ]],
            ]],
        ]];
        $calculated = app(OfferFeeCalculator::class)->calculate(
            [[
                'id' => 1,
                'label' => 'Jasa Aset 1',
                'quantity' => 1,
                'unit_amount' => 1_000_000,
            ]],
            'excluded',
            1100,
            200,
            [[
                'sequence' => 1,
                'percentage_bps' => 10_000,
                'trigger_text' => 'Setelah laporan selesai',
            ]],
        );
        $calculated['line_items'][0]['offer_asset_id'] = 11;
        $calculated['fee_presentation'] = 'per_asset';
        $calculated['amount_in_words'] = app(IndonesianAmountSpeller::class)->spell(
            $calculated['document_payable_total'],
            'IDR',
        );
        $calculated['calculation_errors'] = [];
        $calculated['exposure_rows'] = [[
            'offer_asset_id' => 11,
            'exposure_amount' => 700_000_000,
            'reference_market_value' => 1_000_000_000,
            'reference_liquidation_value' => 700_000_000,
            'liquidation_discount_bps' => 3000,
        ]];
        $snapshot['commercial'] = $calculated;
        $snapshot['requirements'] = [['description_snapshot' => 'Salinan sertifikat']];
        $snapshot['issuer']['letterhead'] = [
            'configured' => true,
            'verified' => true,
            'path' => 'kop.png',
            'mime' => 'image/png',
            'sha256' => str_repeat('d', 64),
        ];
        $snapshot['metadata']['issuer_profile']['letterhead_verified'] = true;

        $result = app(OfferPreflightValidator::class)->validate(
            $snapshot,
            OfferPreflightValidator::MODE_PRINT_READY,
        );

        $this->assertSame([], $result['errors']);

        $snapshot['subjects'][0]['assets'][0]['liquidation_discount_bps'] = 1000;
        $snapshot['commercial']['line_items'][] = $snapshot['commercial']['line_items'][0];
        $result = app(OfferPreflightValidator::class)->validate(
            $snapshot,
            OfferPreflightValidator::MODE_PRINT_READY,
        );

        $this->assertContains('Diskon likuidasi tidak konsisten dengan Nilai Pasar dan Nilai Likuidasi.', $result['errors']);
        $this->assertContains('Template lelang mewajibkan tepat satu item fee untuk setiap aset.', $result['errors']);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $blockFixtures = [
            ['type' => 'text', 'text' => 'Redaksi resmi.'],
            ['type' => 'bullets', 'items' => ['Butir pertama.', 'Butir kedua.']],
            ['type' => 'dynamic', 'source' => 'client', 'text' => 'PT Klien'],
            ['type' => 'asset_list', 'rows' => [[
                'number' => '1', 'subject' => 'PT Debitur', 'asset' => 'Tanah dan bangunan',
                'location' => 'Jakarta', 'documents' => 'SHM 001',
            ]]],
            ['type' => 'fee_summary', 'rows' => [[
                'label' => 'Jumlah penawaran', 'value' => 'Rp1.110.000',
            ]], 'amount_in_words' => 'Satu juta seratus sepuluh ribu rupiah'],
            ['type' => 'fee_table', 'rows' => [[
                'number' => '1', 'asset' => 'Tanah dan bangunan', 'label' => 'Jasa Penilaian',
                'quantity' => '1', 'unit_amount' => 'Rp1.000.000', 'line_total' => 'Rp1.000.000',
            ]]],
            ['type' => 'payment_terms', 'rows' => [[
                'number' => '1', 'percentage' => '100%', 'trigger' => 'Laporan selesai',
                'due' => '7 hari kalender', 'amount' => 'Rp1.110.000',
            ]]],
            ['type' => 'requirements', 'rows' => [[
                'number' => '1', 'code' => 'SHM', 'description' => 'Salinan sertifikat', 'emphasis' => 'normal',
            ]]],
            ['type' => 'exposure_table', 'rows' => [[
                'number' => '1', 'asset' => 'Tanah dan bangunan', 'exposure' => 'Rp700.000.000',
                'market_value' => 'Rp1.000.000.000', 'liquidation_value' => 'Rp700.000.000', 'discount' => '30%',
            ]], 'empty_message' => 'Data belum tersedia'],
        ];
        $clauses = [];

        foreach (config('offer-documents.clause_titles') as $key => $title) {
            $clauses[] = [
                'number' => count($clauses) + 1,
                'key' => $key,
                'title' => $title,
                'paragraphs' => [],
                'items' => [],
                'blocks' => [$blockFixtures[count($clauses)] ?? $blockFixtures[0]],
            ];
        }

        $approved = static fn (string $hash): array => [
            'status' => 'approved',
            'approved_by' => 1,
            'approved_at' => '2026-08-19T09:00:00+07:00',
            'checksum' => str_repeat($hash, 64),
            'is_effective' => true,
            'integrity_valid' => true,
        ];

        return [
            'document' => [
                'number' => '001/TEST', 'place' => 'Jakarta', 'date' => '19 Agustus 2026',
                'subject' => 'Penawaran Lelang', 'opening' => 'Dengan hormat.', 'closing' => 'Demikian penawaran ini.',
            ],
            'issuer' => [
                'name' => 'KJPP Contoh', 'address_lines' => ['Jl. Kantor 1'],
                'contact_lines' => ['021-000'], 'letterhead' => [],
            ],
            'recipient' => ['name' => 'PT Klien', 'attention' => 'Direktur', 'address_lines' => ['Jl. Klien 1']],
            'clauses' => $clauses,
            'signatures' => [
                'issuer_name' => 'Penilai Contoh', 'issuer_title' => 'Partner',
                'issuer_permit_no' => 'IZIN-001', 'issuer_registration_no' => 'REG-001',
                'client_name' => 'PT Klien', 'client_title' => 'Direktur',
            ],
            'metadata' => [
                'schema_version' => 2,
                'number_allocation' => ['status' => 'allocated'],
                'template' => [
                    ...$approved('a'),
                    'template_active' => true,
                    'schema_valid' => true,
                    'schema_version' => 2,
                    'layout_version' => 'offer-a4-v2',
                    'category' => 'property-auction',
                    'constraints' => [
                        'required_engagement_fields' => [],
                        'purpose_must_equal' => 'Pelaksanaan lelang',
                        'valuation_basis_must_equal' => 'Nilai Pasar dan Nilai Likuidasi',
                        'required_asset_document' => true,
                        'require_fee_per_asset' => true,
                        'requires_liquidation_value' => true,
                        'requires_exposure_table' => true,
                    ],
                ],
                'issuer_profile' => $approved('b'),
                'signer' => $approved('c'),
                'uses_provisional_copy' => false,
                'uses_provisional_issuer' => false,
            ],
        ];
    }
}
