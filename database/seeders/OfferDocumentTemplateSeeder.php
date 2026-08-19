<?php

namespace Database\Seeders;

use App\Enums\OfferFeePresentation;
use App\Enums\OfferTemplateBlockType;
use App\Enums\OfferTemplateCategory;
use App\Enums\OfferTemplateDynamicSource;
use App\Models\OfferTemplate;
use App\Services\Offers\OfferTemplateSchemaV2;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OfferDocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->definitions() as $definition) {
                $template = OfferTemplate::query()->firstOrCreate(
                    ['code' => $definition['code']],
                    [
                        'name' => $definition['name'],
                        'purpose' => $definition['purpose'],
                        'category' => $definition['category'],
                        'active' => true,
                        'is_default' => false,
                    ],
                );

                // Never overwrite a Sysadmin-edited or reviewed version.
                $template->versions()->firstOrCreate(
                    ['version_no' => 1],
                    [
                        'schema_version' => OfferTemplateSchemaV2::SCHEMA_VERSION,
                        'clause_schema' => $this->schema(
                            $definition['category'],
                            $definition['purpose'],
                            $definition['valuation_basis'],
                        ),
                        'condition_schema' => null,
                        'layout_version' => OfferTemplateSchemaV2::LAYOUT_VERSION,
                        'header_mode' => OfferTemplateSchemaV2::HEADER_MODE,
                        'status' => 'draft',
                        'effective_from' => null,
                        'effective_until' => null,
                    ],
                );
            }
        });
    }

    /** @return list<array{code:string,name:string,purpose:string,category:string,valuation_basis:string}> */
    private function definitions(): array
    {
        return [
            [
                'code' => 'property-collateral',
                'name' => 'Penjaminan Utang / Properti',
                'purpose' => 'Penjaminan utang',
                'category' => OfferTemplateCategory::PropertyCollateral->value,
                'valuation_basis' => 'Nilai Pasar',
            ],
            [
                'code' => 'property-auction',
                'name' => 'Lelang Properti',
                'purpose' => 'Pelaksanaan lelang',
                'category' => OfferTemplateCategory::PropertyAuction->value,
                'valuation_basis' => 'Nilai Pasar dan Nilai Likuidasi',
            ],
            [
                'code' => 'property-rental',
                'name' => 'Nilai Sewa Pasar',
                'purpose' => 'Penentuan nilai sewa pasar',
                'category' => OfferTemplateCategory::PropertyRental->value,
                'valuation_basis' => 'Nilai Sewa Pasar',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function schema(string $category, string $purpose, string $valuationBasis): array
    {
        $perAsset = $category === OfferTemplateCategory::PropertyAuction->value;
        $rental = $category === OfferTemplateCategory::PropertyRental->value;

        return [
            'document' => [
                'opening' => 'Dengan hormat, sehubungan dengan permintaan jasa penilaian yang kami terima, bersama ini kami sampaikan ruang lingkup dan ketentuan penugasan untuk {{recipient.name}}.',
                'closing' => 'Demikian penawaran ini kami sampaikan. Penugasan mulai berlaku setelah ketentuan komersial dan kelengkapan data disepakati para pihak.',
            ],
            'defaults' => [
                'subject' => $rental
                    ? 'Penawaran Jasa Penilaian Nilai Sewa Pasar'
                    : ($perAsset ? 'Penawaran Jasa Penilaian untuk Lelang Properti' : 'Penawaran Jasa Penilaian Properti'),
                'ownership_form' => 'Hak atas tanah dan/atau bangunan sesuai dokumen kepemilikan',
                'currency' => 'IDR',
                'purpose' => $purpose,
                'valuation_basis' => $valuationBasis,
                'investigation_level' => 'full',
                'report_format' => 'complete',
                'report_language' => 'id',
                'report_copies' => 2,
                'completion_days' => $perAsset ? 15 : 10,
                'completion_day_type' => 'business',
                'tax_inclusion' => 'excluded',
                'ppn_rate_bps' => 1100,
                'pph_rate_bps' => 200,
                'fee_presentation' => $perAsset
                    ? OfferFeePresentation::PerAsset->value
                    : OfferFeePresentation::LumpSum->value,
                'cost_inclusions' => [
                    'Inspeksi lapangan dalam wilayah penugasan',
                    'Penyusunan laporan penilaian',
                ],
                'special_assumptions' => null,
                'payment_terms' => $rental
                    ? [[
                        'percentage_bps' => 10_000,
                        'trigger_text' => 'Setelah laporan penilaian diselesaikan',
                        'due_days' => 7,
                    ]]
                    : [
                        [
                            'percentage_bps' => 5_000,
                            'trigger_text' => 'Setelah penawaran disetujui',
                            'due_days' => 7,
                        ],
                        [
                            'percentage_bps' => 5_000,
                            'trigger_text' => 'Setelah laporan penilaian diselesaikan',
                            'due_days' => 7,
                        ],
                    ],
                'requirements' => [
                    [
                        'requirement_code' => 'OWNERSHIP',
                        'description' => 'Salinan dokumen kepemilikan setiap aset yang akan dinilai',
                        'emphasis_style' => 'normal',
                    ],
                    [
                        'requirement_code' => 'TAX',
                        'description' => 'Salinan dokumen pajak bumi dan bangunan terakhir yang tersedia',
                        'emphasis_style' => 'normal',
                    ],
                    [
                        'requirement_code' => 'SITE_ACCESS',
                        'description' => 'Akses dan pendamping untuk pelaksanaan inspeksi objek penilaian',
                        'emphasis_style' => 'normal',
                    ],
                ],
            ],
            'clauses' => $this->clauses($perAsset, $rental),
            'constraints' => [
                'required_engagement_fields' => OfferTemplateSchemaV2::REQUIRED_ENGAGEMENT_FIELDS,
                'purpose_must_equal' => $purpose,
                'valuation_basis_must_equal' => $valuationBasis,
                'required_asset_document' => true,
                'require_fee_per_asset' => $perAsset,
                'requires_liquidation_value' => $perAsset,
                'requires_exposure_table' => $perAsset,
            ],
        ];
    }

    /** @return array<string, array{blocks:list<array<string, mixed>>}> */
    private function clauses(bool $perAsset, bool $rental): array
    {
        $text = static fn (string $value): array => [
            'type' => OfferTemplateBlockType::Text->value,
            'text' => $value,
        ];
        $dynamic = static fn (OfferTemplateDynamicSource $source, ?string $when = null): array => array_filter([
            'type' => OfferTemplateBlockType::Dynamic->value,
            'source' => $source->value,
            'when' => $when,
        ], static fn (mixed $value): bool => $value !== null);
        $simple = static fn (OfferTemplateBlockType $type): array => ['type' => $type->value];

        return [
            'appraiser_status' => ['blocks' => [
                $dynamic(OfferTemplateDynamicSource::AppraiserStatus),
            ]],
            'client' => ['blocks' => [
                $dynamic(OfferTemplateDynamicSource::Client),
            ]],
            'report_user' => ['blocks' => [
                $dynamic(OfferTemplateDynamicSource::ReportUser),
            ]],
            'valuation_object' => ['blocks' => array_values(array_filter([
                $simple(OfferTemplateBlockType::AssetList),
                $perAsset ? $simple(OfferTemplateBlockType::ExposureTable) : null,
            ]))],
            'ownership_form' => ['blocks' => [
                $dynamic(OfferTemplateDynamicSource::OwnershipForm),
            ]],
            'currency' => ['blocks' => [
                $dynamic(OfferTemplateDynamicSource::Currency),
            ]],
            'purpose' => ['blocks' => [
                $dynamic(OfferTemplateDynamicSource::Purpose),
            ]],
            'basis_of_value' => ['blocks' => [
                $dynamic(OfferTemplateDynamicSource::ValuationBasis),
            ]],
            'valuation_date' => ['blocks' => [
                $dynamic(OfferTemplateDynamicSource::ValuationDate),
            ]],
            'investigation_depth' => ['blocks' => [
                $dynamic(OfferTemplateDynamicSource::InvestigationLevel),
            ]],
            'information_sources' => ['blocks' => [
                $text('Informasi penugasan bersumber dari dokumen yang disampaikan, hasil inspeksi, wawancara, dan data pasar yang dinilai relevan serta dapat dipercaya.'),
            ]],
            'assumptions' => ['blocks' => [
                $text('Penilaian dilaksanakan dengan asumsi bahwa dokumen dan informasi yang diberikan adalah benar, lengkap, dan dapat dipertanggungjawabkan.'),
                $dynamic(OfferTemplateDynamicSource::SpecialAssumptions, 'has_special_assumptions'),
            ]],
            'publication_approval' => ['blocks' => [
                $text('Laporan atau bagian darinya tidak boleh dipublikasikan untuk tujuan lain tanpa persetujuan tertulis dari penilai.'),
            ]],
            'valuation_standard' => ['blocks' => [
                $text('Penilaian dilaksanakan dengan mengacu pada Standar Penilaian Indonesia dan ketentuan profesi yang berlaku pada tanggal penilaian.'),
            ]],
            'valuation_report' => ['blocks' => [
                $dynamic(OfferTemplateDynamicSource::ReportSpecification),
            ]],
            'liability_limit' => ['blocks' => [
                $text('Tanggung jawab profesional dibatasi kepada Pemberi Tugas dan Pengguna Laporan yang disebutkan dalam dokumen penugasan.'),
            ]],
            'client_declaration' => ['blocks' => [
                $text('Pemberi Tugas menyatakan bahwa seluruh informasi material yang disampaikan kepada penilai adalah benar dan tidak menyesatkan.'),
            ]],
            'professional_fee' => ['blocks' => [
                $simple($perAsset ? OfferTemplateBlockType::FeeTable : OfferTemplateBlockType::FeeSummary),
                $simple(OfferTemplateBlockType::PaymentTerms),
            ]],
            'initial_data_request' => ['blocks' => [
                $simple(OfferTemplateBlockType::Requirements),
            ]],
            'completion_time' => ['blocks' => [
                $dynamic(OfferTemplateDynamicSource::CompletionTime),
            ]],
            'assignment_procedure' => ['blocks' => [[
                'type' => OfferTemplateBlockType::Bullets->value,
                'items' => [
                    'Konfirmasi ruang lingkup dan penerimaan data awal',
                    'Inspeksi objek penilaian',
                    $rental ? 'Analisis pasar sewa dan penyusunan laporan' : 'Analisis data pasar dan penyusunan laporan',
                    'Review mutu sebelum laporan diterbitkan',
                ],
            ]]],
            'cancellation' => ['blocks' => [
                $text('Pembatalan setelah pekerjaan dimulai dapat dikenakan biaya sesuai pekerjaan yang telah dilaksanakan dan pengeluaran yang telah terjadi.'),
            ]],
            'confidentiality' => ['blocks' => [
                $text('Informasi yang diterima dalam penugasan dijaga kerahasiaannya sesuai ketentuan hukum dan standar profesi yang berlaku.'),
            ]],
            'closing' => ['blocks' => [
                $text('Persetujuan atas penawaran ini menjadi dasar dimulainya proses administrasi dan pelaksanaan penugasan.'),
            ]],
            'other_terms' => ['blocks' => [
                $text('Hal yang belum diatur dalam penawaran ini akan disepakati secara tertulis oleh para pihak tanpa mengubah independensi penilai.'),
            ]],
        ];
    }
}
