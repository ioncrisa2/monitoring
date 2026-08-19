<?php

namespace Tests\Unit\Offers;

use App\Models\Branch;
use App\Models\Debtor;
use App\Models\DocumentSignerVersion;
use App\Models\IssuerProfileVersion;
use App\Models\Offer;
use App\Models\OfferAsset;
use App\Models\OfferAssetDocument;
use App\Models\OfferEngagement;
use App\Models\OfferFeeItem;
use App\Models\OfferPaymentTerm;
use App\Models\OfferRequirement;
use App\Models\OfferSubject;
use App\Models\OfferTemplateVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\Offers\OfferSnapshotBuilder;
use Database\Seeders\OfferDocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferSnapshotBuilderV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_an_auction_template_into_safe_human_readable_blocks(): void
    {
        $this->seed(OfferDocumentTemplateSeeder::class);

        $branch = Branch::create(['code' => 'JKT', 'number_code' => 10, 'name' => 'Jakarta', 'active' => true]);
        $debtor = Debtor::create(['name' => 'PT Debitur', 'identifier' => 'D-001', 'address' => 'Jl. Debitur']);
        $client = Organization::create(['name' => 'PT Klien', 'type' => 'pemberi_tugas', 'address' => 'Jl. Klien']);
        $user = User::create([
            'branch_id' => $branch->getKey(), 'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'role' => 'admin', 'active' => true,
        ]);
        $offer = Offer::create([
            'offer_no' => '001/TEST/VIII/2026', 'sequence_no' => 1, 'offer_date' => '2026-08-19',
            'branch_id' => $branch->getKey(), 'debtor_id' => $debtor->getKey(),
            'client_id' => $client->getKey(), 'fee' => 1_000_000, 'created_by' => $user->getKey(),
        ]);
        $template = OfferTemplateVersion::query()
            ->whereHas('template', fn ($query) => $query->where('code', 'property-auction'))
            ->firstOrFail();
        $issuer = IssuerProfileVersion::create([
            'branch_id' => $branch->getKey(), 'version_no' => 1, 'legal_name' => 'KJPP Contoh',
            'permit_no' => 'IZIN-001', 'address' => 'Jl. Kantor', 'city' => 'Jakarta',
        ]);
        $signer = DocumentSignerVersion::create([
            'branch_id' => $branch->getKey(), 'signer_key' => 'partner', 'version_no' => 1,
            'full_name' => 'Penilai Contoh', 'position' => 'Partner', 'permit_no' => 'IZIN-001',
        ]);
        OfferEngagement::create([
            'offer_id' => $offer->getKey(), 'template_version_id' => $template->getKey(),
            'issuer_profile_version_id' => $issuer->getKey(), 'signer_version_id' => $signer->getKey(),
            'issue_city' => 'Jakarta', 'recipient_attention' => 'Direktur',
            'recipient_organization' => 'PT Klien', 'recipient_address' => 'Jl. Klien',
            'recipient_city' => 'Jakarta', 'subject' => 'Penawaran Lelang',
            'ownership_form' => 'Hak Milik', 'currency' => 'IDR', 'purpose' => 'Pelaksanaan lelang',
            'valuation_basis' => 'Nilai Pasar dan Nilai Likuidasi', 'valuation_date' => '2026-08-19',
            'investigation_level' => 'full', 'report_format' => 'complete', 'report_language' => 'id',
            'report_copies' => 2, 'completion_days' => 15, 'completion_day_type' => 'business',
            'tax_inclusion' => 'excluded', 'fee_presentation' => 'per_asset',
            'ppn_rate_bps' => 1100, 'pph_rate_bps' => 200,
        ]);
        $subject = OfferSubject::create([
            'offer_id' => $offer->getKey(), 'debtor_id' => $debtor->getKey(),
            'name_snapshot' => 'PT Debitur', 'primary_slot' => 1, 'sort_order' => 0,
        ]);
        $asset = OfferAsset::create([
            'offer_subject_id' => $subject->getKey(), 'asset_type' => 'tanah',
            'description' => 'Tanah dan bangunan', 'address' => 'Jl. Aset 1', 'city' => 'Jakarta',
            'land_area_m2' => '1000.50', 'exposure_amount' => 700_000_000,
            'reference_market_value' => 1_000_000_000, 'reference_liquidation_value' => 700_000_000,
            'liquidation_discount_bps' => 3000, 'sort_order' => 0,
        ]);
        OfferAssetDocument::create([
            'offer_asset_id' => $asset->getKey(), 'document_type' => 'SHM', 'document_no' => '001',
            'primary_slot' => 1, 'sort_order' => 0,
        ]);
        OfferFeeItem::create([
            'offer_id' => $offer->getKey(), 'offer_subject_id' => $subject->getKey(),
            'offer_asset_id' => $asset->getKey(), 'label' => 'Jasa Penilaian Aset 1',
            'quantity' => 1, 'unit_amount' => 1_000_000, 'sort_order' => 0,
        ]);
        OfferPaymentTerm::create([
            'offer_id' => $offer->getKey(), 'sequence' => 1, 'percentage_bps' => 10_000,
            'trigger_text' => 'Setelah laporan selesai', 'due_days' => 7,
        ]);
        OfferRequirement::create([
            'offer_id' => $offer->getKey(), 'requirement_code' => 'SHM',
            'description_snapshot' => 'Salinan sertifikat', 'emphasis_style' => 'normal', 'sort_order' => 0,
        ]);

        $snapshot = app(OfferSnapshotBuilder::class)->build($offer);
        $valuationObject = collect($snapshot['clauses'])->firstWhere('key', 'valuation_object');
        $professionalFee = collect($snapshot['clauses'])->firstWhere('key', 'professional_fee');

        $this->assertSame(2, $snapshot['metadata']['schema_version']);
        $this->assertSame('offer-a4-v2', $snapshot['metadata']['template']['layout_version']);
        $this->assertSame('property-auction', $snapshot['metadata']['template']['category']);
        $this->assertSame('19 Agustus 2026', $snapshot['document']['date']);
        $this->assertSame(['asset_list', 'exposure_table'], array_column($valuationObject['blocks'], 'type'));
        $this->assertSame('Rp700.000.000', $valuationObject['blocks'][1]['rows'][0]['liquidation_value']);
        $this->assertSame('30%', $valuationObject['blocks'][1]['rows'][0]['discount']);
        $this->assertSame(['fee_table', 'payment_terms'], array_column($professionalFee['blocks'], 'type'));
        $this->assertSame('Tanah dan bangunan (LT 1.000,5 m²)', $professionalFee['blocks'][0]['rows'][0]['asset']);
        $this->assertSame('15 hari kerja', collect($snapshot['clauses'])->firstWhere('key', 'completion_time')['blocks'][0]['text']);
        $this->assertFalse(str_contains(json_encode($snapshot['clauses'], JSON_THROW_ON_ERROR), '{{'));
    }
}
