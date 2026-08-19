<?php

namespace Tests\Support\Offers;

use InvalidArgumentException;

/**
 * Deterministic, anonymous snapshots used to guard the four reference layouts.
 *
 * These fixtures intentionally model only the page shapes observed during the
 * visual audit. They do not contain text, names, identifiers, or artwork copied
 * from customer documents.
 */
final class AnonymousOfferVisualFixtureFactory
{
    /**
     * @return array<string, array{
     *     label: string,
     *     reference_shape: string,
     *     category: string,
     *     expected_pages: int,
     *     asset_count: int,
     *     prose_repetitions: int,
     *     extended_prose_clauses: int
     * }>
     */
    public static function manifest(): array
    {
        return [
            'collateral-multi' => [
                'label' => 'Agunan properti multi-aset',
                'reference_shape' => 'Caraka (5 halaman)',
                'category' => 'property-collateral',
                'expected_pages' => 5,
                'asset_count' => 5,
                'prose_repetitions' => 1,
                'extended_prose_clauses' => 0,
            ],
            'collateral-detailed' => [
                'label' => 'Agunan properti dengan uraian rinci',
                'reference_shape' => 'Bank Index (6 halaman)',
                'category' => 'property-collateral',
                'expected_pages' => 6,
                'asset_count' => 7,
                'prose_repetitions' => 1,
                'extended_prose_clauses' => 7,
            ],
            'auction-twelve-assets' => [
                'label' => 'Lelang dua belas aset',
                'reference_shape' => 'Lelang Mandiri (9 halaman)',
                'category' => 'property-auction',
                'expected_pages' => 9,
                'asset_count' => 12,
                'prose_repetitions' => 1,
                'extended_prose_clauses' => 7,
            ],
            'rental-market' => [
                'label' => 'Nilai sewa pasar',
                'reference_shape' => 'Nilai Sewa (5 halaman)',
                'category' => 'property-rental',
                'expected_pages' => 5,
                'asset_count' => 2,
                'prose_repetitions' => 1,
                'extended_prose_clauses' => 3,
            ],
        ];
    }

    /**
     * @param  array{path: string, mime: string, sha256: string}  $letterhead
     * @return array<string, mixed>
     */
    public static function make(string $key, array $letterhead): array
    {
        $definition = self::manifest()[$key] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Fixture visual {$key} tidak dikenal.");
        }

        $assets = self::assets($definition['asset_count']);
        $clauses = self::clauses(
            $definition['category'],
            $definition['prose_repetitions'],
            $definition['extended_prose_clauses'],
            $assets,
        );
        $approved = static fn (string $character): array => [
            'status' => 'approved',
            'approved_by' => 9001,
            'approved_at' => '2026-08-19T09:00:00+07:00',
            'checksum' => str_repeat($character, 64),
            'is_effective' => true,
            'integrity_valid' => true,
        ];

        return [
            'document' => [
                'number' => 'VISUAL/'.strtoupper(str_replace('-', '/', $key)).'/2026',
                'place' => 'Kota Contoh',
                'date' => '19 Agustus 2026',
                'subject' => self::subject($definition['category']),
                'opening' => 'Dengan hormat, berdasarkan permintaan penugasan yang telah diterima, kami menyampaikan penawaran jasa penilaian berikut untuk ditinjau dan disetujui.',
                'closing' => 'Demikian penawaran ini kami sampaikan. Kami mengucapkan terima kasih atas perhatian dan kepercayaan yang diberikan.',
            ],
            'issuer' => [
                'name' => 'Kantor Jasa Penilai Publik Contoh',
                'address_lines' => ['Jalan Pengujian Nomor 1, Kota Contoh'],
                'contact_lines' => ['Telepon 000-000000', 'dokumen@example.test'],
                'letterhead' => [
                    'configured' => true,
                    'verified' => true,
                    ...$letterhead,
                ],
            ],
            'recipient' => [
                'name' => 'PT Pemberi Tugas Anonim',
                'attention' => 'Direksi',
                'address_lines' => ['Jalan Klien Contoh Nomor 2', 'Kota Contoh'],
            ],
            'clauses' => $clauses,
            'signatures' => [
                'issuer_name' => 'Penilai Contoh',
                'issuer_title' => 'Rekan',
                'issuer_permit_no' => 'IZIN-UJI-001',
                'issuer_registration_no' => 'REG-UJI-001',
                'client_name' => 'PT Pemberi Tugas Anonim',
                'client_title' => 'Direktur',
            ],
            'metadata' => [
                'schema_version' => 2,
                'fixture' => [
                    'key' => $key,
                    'expected_pages' => $definition['expected_pages'],
                    'raster_dpi' => 144,
                    'contains_customer_data' => false,
                ],
                'number_allocation' => ['status' => 'allocated'],
                'template' => [
                    ...$approved('a'),
                    'template_active' => true,
                    'schema_valid' => true,
                    'schema_version' => 2,
                    'layout_version' => 'offer-a4-v2',
                    'category' => $definition['category'],
                ],
                'issuer_profile' => [
                    ...$approved('b'),
                    'letterhead_verified' => true,
                ],
                'signer' => $approved('c'),
                'uses_provisional_copy' => false,
                'uses_provisional_issuer' => false,
            ],
        ];
    }

    /** @return list<array<string, string>> */
    private static function assets(int $count): array
    {
        $assets = [];

        for ($index = 1; $index <= $count; $index++) {
            $assets[] = [
                'number' => (string) $index,
                'subject' => 'Subjek Anonim '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'asset' => 'Tanah dan bangunan komersial seluas '.number_format(700 + ($index * 125), 0, ',', '.').' m²',
                'location' => 'Jalan Objek Contoh Nomor '.$index.', Kota Contoh',
                'documents' => 'Sertifikat Uji '.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            ];
        }

        return $assets;
    }

    /**
     * @param  list<array<string, string>>  $assets
     * @return list<array<string, mixed>>
     */
    private static function clauses(
        string $category,
        int $repetitions,
        int $extendedProseClauses,
        array $assets,
    ): array {
        $clauses = [];
        $proseOrdinal = 0;

        foreach (config('offer-documents.clause_titles') as $key => $title) {
            $blocks = [[
                'type' => 'text',
                'text' => self::prose($key, $repetitions + ($proseOrdinal < $extendedProseClauses ? 1 : 0)),
            ]];
            $usesGeneratedProse = true;

            if ($key === 'client') {
                $blocks = [['type' => 'dynamic', 'source' => 'client', 'text' => 'PT Pemberi Tugas Anonim, berkedudukan di Kota Contoh.']];
                $usesGeneratedProse = false;
            } elseif ($key === 'report_user') {
                $blocks = [['type' => 'dynamic', 'source' => 'report_user', 'text' => 'Pemberi tugas dan pihak yang dinyatakan secara tertulis dalam laporan.']];
                $usesGeneratedProse = false;
            } elseif ($key === 'valuation_object') {
                $blocks = [['type' => 'asset_list', 'rows' => $assets]];
                $usesGeneratedProse = false;

                if ($category === 'property-auction') {
                    $blocks[] = ['type' => 'exposure_table', 'rows' => self::exposureRows($assets), 'empty_message' => 'Data tidak tersedia.'];
                }
            } elseif ($key === 'professional_fee') {
                $usesGeneratedProse = false;
                $blocks = $category === 'property-auction'
                    ? [
                        ['type' => 'fee_table', 'rows' => self::feeRows($assets)],
                        ['type' => 'payment_terms', 'rows' => self::paymentRows()],
                    ]
                    : [
                        ['type' => 'fee_summary', 'rows' => self::summaryRows(count($assets)), 'amount_in_words' => 'Dua puluh tujuh juta tujuh ratus lima puluh ribu rupiah'],
                        ['type' => 'payment_terms', 'rows' => self::paymentRows()],
                    ];
            } elseif ($key === 'initial_data_request') {
                $blocks = [['type' => 'requirements', 'rows' => self::requirementRows($category)]];
                $usesGeneratedProse = false;
            } elseif ($key === 'completion_time') {
                $blocks = [['type' => 'dynamic', 'source' => 'completion_time', 'text' => 'Pekerjaan diselesaikan dalam 15 hari kerja setelah data lengkap diterima.']];
                $usesGeneratedProse = false;
            } elseif ($key === 'basis_of_value') {
                $usesGeneratedProse = false;
                $blocks = [[
                    'type' => 'bullets',
                    'items' => $category === 'property-auction'
                        ? ['Nilai Pasar sesuai standar penilaian yang berlaku.', 'Nilai Likuidasi untuk kebutuhan pelaksanaan lelang.']
                        : [$category === 'property-rental' ? 'Nilai Sewa Pasar.' : 'Nilai Pasar.'],
                ]];
            }

            $clauses[] = [
                'number' => count($clauses) + 1,
                'key' => $key,
                'title' => $title,
                'paragraphs' => [],
                'items' => [],
                'blocks' => $blocks,
            ];

            if ($usesGeneratedProse) {
                $proseOrdinal++;
            }
        }

        return $clauses;
    }

    private static function prose(string $clauseKey, int $repetitions): string
    {
        $sentence = 'Ketentuan pengujian ini menjelaskan ruang lingkup, batas penggunaan, sumber informasi, dan tanggung jawab para pihak secara konsisten. ';
        $context = 'Setiap data wajib dapat diverifikasi, dicatat dalam berkas penugasan, dan digunakan sesuai standar penilaian yang berlaku.';

        return ucfirst(str_replace('_', ' ', $clauseKey)).': '.trim(str_repeat($sentence.$context.' ', $repetitions));
    }

    /** @param list<array<string, string>> $assets */
    private static function exposureRows(array $assets): array
    {
        return array_map(static function (array $asset, int $index): array {
            $market = 1_000_000_000 + ($index * 175_000_000);
            $liquidation = (int) round($market * 0.7);

            return [
                'number' => $asset['number'],
                'asset' => $asset['asset'],
                'exposure' => 'Rp'.number_format((int) round($market * 0.65), 0, ',', '.'),
                'market_value' => 'Rp'.number_format($market, 0, ',', '.'),
                'liquidation_value' => 'Rp'.number_format($liquidation, 0, ',', '.'),
                'discount' => '30%',
            ];
        }, $assets, array_keys($assets));
    }

    /** @param list<array<string, string>> $assets */
    private static function feeRows(array $assets): array
    {
        return array_map(static fn (array $asset): array => [
            'number' => $asset['number'],
            'asset' => $asset['asset'],
            'label' => 'Jasa penilaian objek '.str_pad($asset['number'], 2, '0', STR_PAD_LEFT),
            'quantity' => '1',
            'unit_amount' => 'Rp2.500.000',
            'line_total' => 'Rp2.500.000',
        ], $assets);
    }

    /** @return list<array<string, string>> */
    private static function paymentRows(): array
    {
        return [[
            'number' => '1',
            'percentage' => '100%',
            'trigger' => 'Setelah laporan penilaian selesai',
            'due' => '7 hari kalender',
            'amount' => 'Rp27.750.000',
        ]];
    }

    /** @return list<array<string, string>> */
    private static function summaryRows(int $assetCount): array
    {
        return [
            ['label' => "Biaya jasa untuk {$assetCount} objek", 'value' => 'Rp25.000.000'],
            ['label' => 'PPN 11%', 'value' => 'Rp2.750.000'],
            ['label' => 'Jumlah penawaran', 'value' => 'Rp27.750.000'],
        ];
    }

    /** @return list<array<string, string>> */
    private static function requirementRows(string $category): array
    {
        $rows = [
            ['number' => '1', 'code' => 'LEGAL', 'description' => 'Salinan dokumen legal kepemilikan objek.', 'emphasis' => 'normal'],
            ['number' => '2', 'code' => 'TEKNIS', 'description' => 'Data teknis bangunan dan denah lokasi.', 'emphasis' => 'normal'],
            ['number' => '3', 'code' => 'FOTO', 'description' => 'Dokumentasi kondisi objek pada tanggal inspeksi.', 'emphasis' => 'normal'],
            ['number' => '4', 'code' => 'PAJAK', 'description' => 'Dokumen pajak bumi dan bangunan terbaru.', 'emphasis' => 'normal'],
        ];

        if ($category === 'property-auction') {
            $rows[] = ['number' => '5', 'code' => 'EXPOSURE', 'description' => 'Data exposure kredit per objek yang telah direkonsiliasi.', 'emphasis' => 'bold'];
            $rows[] = ['number' => '6', 'code' => 'LELANG', 'description' => 'Ketentuan dan jadwal pelaksanaan lelang.', 'emphasis' => 'normal'];
        }

        return $rows;
    }

    private static function subject(string $category): string
    {
        return match ($category) {
            'property-auction' => 'Penawaran Jasa Penilaian untuk Lelang',
            'property-rental' => 'Penawaran Jasa Penilaian Nilai Sewa Pasar',
            default => 'Penawaran Jasa Penilaian Agunan Properti',
        };
    }
}
