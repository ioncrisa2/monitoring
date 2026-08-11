<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Debtor;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\Offer;
use App\Models\Organization;
use App\Models\Report;
use App\Models\StatusHistory;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAssignment;
use App\Services\OfferNumberService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Branches (number_code dipakai sebagai digit cabang di Nomor Penawaran/Kontrak, 0 = Pusat)
        $pst = Branch::create(['code' => 'PST', 'number_code' => 0, 'name' => 'Kantor Pusat Jakarta', 'active' => true]);
        $jkt = Branch::create(['code' => 'JKT', 'number_code' => 1, 'name' => 'Cabang Jakarta Selatan', 'active' => true]);
        $sub = Branch::create(['code' => 'SUB', 'number_code' => 2, 'name' => 'Cabang Surabaya', 'active' => true]);
        $bdg = Branch::create(['code' => 'BDG', 'number_code' => 3, 'name' => 'Cabang Bandung', 'active' => true]);
        $mdn = Branch::create(['code' => 'MDN', 'number_code' => 4, 'name' => 'Cabang Medan', 'active' => true]);

        // 2. Seed Users
        $sysAdmin = User::create([
            'branch_id' => $pst->id,
            'name' => 'System Admin',
            'email' => 'admin@kjpp.com',
            'password' => Hash::make('password'),
            'role' => 'sysadmin',
            'phone' => '081234567890',
            'active' => true,
        ]);

        $supervisor = User::create([
            'branch_id' => $pst->id,
            'name' => 'Bambang Supervisor',
            'email' => 'supervisor@kjpp.com',
            'password' => Hash::make('password'),
            'role' => 'supervisor',
            'phone' => '081234567891',
            'active' => true,
        ]);

        $opJkt = User::create([
            'branch_id' => $jkt->id,
            'name' => 'Siti Operator JKT',
            'email' => 'operator.jkt@kjpp.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '081234567892',
            'active' => true,
        ]);

        $opBdg = User::create([
            'branch_id' => $bdg->id,
            'name' => 'Rian Operator Bandung',
            'email' => 'operator.bdg@kjpp.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '081234567895',
            'active' => true,
        ]);

        $opMdn = User::create([
            'branch_id' => $mdn->id,
            'name' => 'Farah Operator Medan',
            'email' => 'operator.mdn@kjpp.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '081234567896',
            'active' => true,
        ]);

        $surveyor1 = User::create([
            'branch_id' => $jkt->id,
            'name' => 'Andi Surveyor',
            'email' => 'andi.surveyor@kjpp.com',
            'password' => Hash::make('password'),
            'role' => 'surveyor',
            'phone' => '081234567893',
            'active' => true,
        ]);

        $surveyor2 = User::create([
            'branch_id' => $sub->id,
            'name' => 'Citra Surveyor',
            'email' => 'citra.surveyor@kjpp.com',
            'password' => Hash::make('password'),
            'role' => 'surveyor',
            'phone' => '081234567897',
            'active' => true,
        ]);

        $reviewer1 = User::create([
            'branch_id' => $pst->id,
            'name' => 'Budi Reviewer Senior',
            'email' => 'budi.reviewer@kjpp.com',
            'password' => Hash::make('password'),
            'role' => 'reviewer',
            'phone' => '081234567894',
            'active' => true,
        ]);

        $reviewer2 = User::create([
            'branch_id' => $jkt->id,
            'name' => 'Dewi Reviewer',
            'email' => 'dewi.reviewer@kjpp.com',
            'password' => Hash::make('password'),
            'role' => 'reviewer',
            'phone' => '081234567898',
            'active' => true,
        ]);

        // 3. Seed Organizations
        $bankMandiri = Organization::create([
            'name' => 'PT Bank Mandiri (Persero) Tbk',
            'type' => 'pemberi_tugas',
            'address' => 'Plaza Mandiri, Jl. Jend. Gatot Subroto Kav. 36-38, Jakarta',
            'tax_id' => '01.000.000.0-000.000',
            'phone' => '021-5265000',
        ]);

        $bankBCA = Organization::create([
            'name' => 'PT Bank Central Asia Tbk',
            'type' => 'pemberi_tugas',
            'address' => 'Menara BCA, Grand Indonesia, Jl. M.H. Thamrin No. 1, Jakarta',
            'tax_id' => '01.000.111.1-111.000',
            'phone' => '021-23588000',
        ]);

        $bankBRI = Organization::create([
            'name' => 'PT Bank Rakyat Indonesia (Persero) Tbk',
            'type' => 'pemberi_tugas',
            'address' => 'Gedung BRI 1, Jl. Jend. Sudirman Kav. 44-46, Jakarta',
            'tax_id' => '01.000.222.2-222.000',
            'phone' => '021-5751234',
        ]);

        $bankBNI = Organization::create([
            'name' => 'PT Bank Negara Indonesia (Persero) Tbk',
            'type' => 'pemberi_tugas',
            'address' => 'Grha BNI, Jl. Jend. Sudirman Kav. 1, Jakarta',
            'tax_id' => '01.000.333.3-333.000',
            'phone' => '021-2511946',
        ]);

        $penggunaMandiri = Organization::create([
            'name' => 'Divisi Credit Risk & Special Asset Management',
            'type' => 'pengguna_laporan',
            'address' => 'Plaza Mandiri Lantai 12, Jakarta',
            'tax_id' => '01.000.000.0-000.000',
            'phone' => '021-5265001',
        ]);

        $penggunaBNI = Organization::create([
            'name' => 'Divisi Manajemen Risiko Kredit BNI',
            'type' => 'pengguna_laporan',
            'address' => 'Grha BNI Lantai 8, Jakarta',
            'tax_id' => '01.000.333.3-333.000',
            'phone' => '021-2511947',
        ]);

        // 4. Seed Debtors
        $deb1 = Debtor::create(['name' => 'PT Surya Citra Kencana', 'identifier' => 'DEB-2026-001', 'address' => 'Kawasan Industri Pulogadung, Jakarta Timur']);
        $deb2 = Debtor::create(['name' => 'PT Nusantara Bangun Jaya', 'identifier' => 'DEB-2026-002', 'address' => 'Jl. Boulevard Raya Blok M3, Kelapa Gading, Jakarta Utara']);
        $deb3 = Debtor::create(['name' => 'CV Agung Utama Mandiri', 'identifier' => 'DEB-2026-003', 'address' => 'Jl. Pemuda No. 45, Surabaya']);
        $deb4 = Debtor::create(['name' => 'PT Wisma Asri Realty', 'identifier' => 'DEB-2026-004', 'address' => 'Kawasan BSD City, Tangerang Selatan']);
        $deb5 = Debtor::create(['name' => 'PT Cipta Selaras Property', 'identifier' => 'DEB-2026-005', 'address' => 'Jl. Ir. H. Djuanda No. 12, Bandung']);
        $deb6 = Debtor::create(['name' => 'PT Karya Mandiri Sejahtera', 'identifier' => 'DEB-2026-006', 'address' => 'Jl. Gatot Subroto No. 88, Medan']);

        // 5. Seed Offers
        // Nomor Penawaran/Kontrak di-generate lewat OfferNumberService (sama persis dengan yang dipakai form),
        // nomor urut reset tiap tahun dan digabung lintas cabang.
        $seq = 0;
        $calcTax = function (float $fee, float $ta): array {
            $dpp = max(0, $fee - $ta);

            return [
                'dpp' => $dpp,
                'ppn' => round($dpp * 0.11, 2),
                'pph' => round($dpp * 0.02, 2),
            ];
        };
        $makeOffer = function (Branch $branch, Carbon $offerDate, array $attrs) use (&$seq, $calcTax) {
            $seq++;
            $tax = $calcTax($attrs['fee'], $attrs['ta']);

            return Offer::create(array_merge($attrs, $tax, [
                'sequence_no' => $seq,
                'offer_no' => OfferNumberService::build($seq, $branch->number_code, $offerDate),
                'offer_date' => $offerDate,
                'branch_id' => $branch->id,
            ]));
        };

        // Offer 1: DITERIMA -> WorkOrder SELESAI (laporan sudah terbit & terkirim)
        $off1 = $makeOffer($pst, Carbon::now()->subDays(130), [
            'debtor_id' => $deb1->id, 'client_id' => $bankMandiri->id, 'report_user_id' => $penggunaMandiri->id,
            'fee' => 32000000, 'ta' => 4000000, 'outcome' => 'DITERIMA', 'created_by' => $supervisor->id,
        ]);

        // Offer 2: DITERIMA -> WorkOrder SELESAI (laporan sudah terbit & terkirim)
        $off2 = $makeOffer($jkt, Carbon::now()->subDays(120), [
            'debtor_id' => $deb2->id, 'client_id' => $bankBCA->id, 'report_user_id' => null,
            'fee' => 27000000, 'ta' => 3000000, 'outcome' => 'DITERIMA', 'created_by' => $opJkt->id,
        ]);

        // Offer 3: DITERIMA -> WorkOrder BATAL (kontrak dibatalkan sebelum pekerjaan berjalan)
        $off3 = $makeOffer($sub, Carbon::now()->subDays(110), [
            'debtor_id' => $deb3->id, 'client_id' => $bankBRI->id, 'report_user_id' => null,
            'fee' => 40000000, 'ta' => 4500000, 'outcome' => 'DITERIMA', 'created_by' => $sysAdmin->id,
        ]);

        // Offer 4: DRAFT (belum dikirim ke klien)
        $makeOffer($bdg, Carbon::now()->subDays(100), [
            'debtor_id' => $deb5->id, 'client_id' => $bankMandiri->id, 'report_user_id' => $penggunaMandiri->id,
            'fee' => 22000000, 'ta' => 2500000, 'outcome' => 'DRAFT', 'created_by' => $opBdg->id,
        ]);

        // Offer 5: DITOLAK
        $makeOffer($mdn, Carbon::now()->subDays(90), [
            'debtor_id' => $deb6->id, 'client_id' => $bankBNI->id, 'report_user_id' => $penggunaBNI->id,
            'fee' => 19500000, 'ta' => 2000000, 'outcome' => 'DITOLAK', 'note' => 'Kalah tender dengan KJPP rekanan lain',
            'created_by' => $opMdn->id,
        ]);

        // Offer 6: TIDAK_LANJUT
        $makeOffer($pst, Carbon::now()->subDays(80), [
            'debtor_id' => $deb4->id, 'client_id' => $bankBRI->id, 'report_user_id' => null,
            'fee' => 26000000, 'ta' => 3000000, 'outcome' => 'TIDAK_LANJUT', 'note' => 'Proses appraisal ditunda oleh klien',
            'created_by' => $sysAdmin->id,
        ]);

        // Offer 7: DITERIMA -> WorkOrder CETAK (laporan sudah dicetak, belum dikirim)
        $off7 = $makeOffer($jkt, Carbon::now()->subDays(70), [
            'debtor_id' => $deb1->id, 'client_id' => $bankBCA->id, 'report_user_id' => null,
            'fee' => 48000000, 'ta' => 5000000, 'outcome' => 'DITERIMA', 'created_by' => $opJkt->id,
        ]);

        // Offer 8: DIKIRIM (menunggu keputusan klien)
        $makeOffer($sub, Carbon::now()->subDays(60), [
            'debtor_id' => $deb2->id, 'client_id' => $bankMandiri->id, 'report_user_id' => $penggunaMandiri->id,
            'fee' => 21000000, 'ta' => 2500000, 'outcome' => 'DIKIRIM', 'created_by' => $sysAdmin->id,
        ]);

        // Offer 9: DITERIMA -> WorkOrder REVIEW
        $off9 = $makeOffer($bdg, Carbon::now()->subDays(50), [
            'debtor_id' => $deb3->id, 'client_id' => $bankBNI->id, 'report_user_id' => $penggunaBNI->id,
            'fee' => 33000000, 'ta' => 3500000, 'outcome' => 'DITERIMA', 'created_by' => $opBdg->id,
        ]);

        // Offer 10: DRAFT
        $makeOffer($mdn, Carbon::now()->subDays(42), [
            'debtor_id' => $deb5->id, 'client_id' => $bankBCA->id, 'report_user_id' => null,
            'fee' => 17000000, 'ta' => 2000000, 'outcome' => 'DRAFT', 'created_by' => $opMdn->id,
        ]);

        // Offer 11: DITERIMA -> WorkOrder PENGERJAAN (SLA overdue, demo red-flag)
        $off11 = $makeOffer($pst, Carbon::now()->subDays(35), [
            'debtor_id' => $deb4->id, 'client_id' => $bankBRI->id, 'report_user_id' => null,
            'fee' => 55000000, 'ta' => 6000000, 'outcome' => 'DITERIMA', 'created_by' => $supervisor->id,
        ]);

        // Offer 12: DITERIMA -> WorkOrder SURVEY
        $off12 = $makeOffer($jkt, Carbon::now()->subDays(28), [
            'debtor_id' => $deb6->id, 'client_id' => $bankMandiri->id, 'report_user_id' => $penggunaMandiri->id,
            'fee' => 30000000, 'ta' => 3500000, 'outcome' => 'DITERIMA', 'created_by' => $opJkt->id,
        ]);

        // Offer 13: DIKIRIM
        $makeOffer($sub, Carbon::now()->subDays(15), [
            'debtor_id' => $deb1->id, 'client_id' => $bankBNI->id, 'report_user_id' => $penggunaBNI->id,
            'fee' => 24000000, 'ta' => 2500000, 'outcome' => 'DIKIRIM', 'created_by' => $sysAdmin->id,
        ]);

        // Offer 14: DITERIMA -> WorkOrder PERSIAPAN (baru dibuat)
        $off14 = $makeOffer($bdg, Carbon::now()->subDays(3), [
            'debtor_id' => $deb2->id, 'client_id' => $bankBCA->id, 'report_user_id' => null,
            'fee' => 29000000, 'ta' => 3000000, 'outcome' => 'DITERIMA', 'created_by' => $opBdg->id,
        ]);

        // 6. Seed WorkOrders + Assets + StatusHistory + Assignments + Reports + Deliveries + Documents
        // StatusHistory.created_at menentukan durasi tiap tahap, jadi selalu di-backdate manual ke tanggal
        // transisi sebenarnya (bukan "now") supaya widget Rata-rata Durasi per Tahap punya data yang realistis.
        $startWorkOrder = function (Offer $offer, Carbon $contractDate, User $changedBy, array $extra = []) {
            $wo = WorkOrder::create(array_merge([
                'offer_id' => $offer->id,
                'contract_no' => $offer->offer_no,
                'contract_date' => $contractDate,
                'survey_required' => true,
                'started_at' => $contractDate,
                'current_status' => 'PERSIAPAN',
            ], $extra));

            $history = StatusHistory::create([
                'work_order_id' => $wo->id,
                'from_status' => null,
                'to_status' => 'PERSIAPAN',
                'changed_by' => $changedBy->id,
                'note' => 'Pekerjaan dibuat dari penawaran ' . $offer->offer_no,
            ]);
            $history->created_at = $contractDate;
            $history->updated_at = $contractDate;
            $history->save();

            return $wo;
        };

        $advanceStatus = function (WorkOrder $wo, string $from, string $to, User $changedBy, Carbon $at, ?string $note = null) {
            $history = StatusHistory::create([
                'work_order_id' => $wo->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $changedBy->id,
                'note' => $note,
            ]);
            $history->created_at = $at;
            $history->updated_at = $at;
            $history->save();
        };

        $addAsset = function (WorkOrder $wo, string $type, string $address, string $city, string $province, ?string $description = null) {
            return $wo->assets()->create([
                'asset_type' => $type,
                'address' => $address,
                'city' => $city,
                'province' => $province,
                'description' => $description,
            ]);
        };

        $addDocument = function (WorkOrder $wo, string $title, string $type, User $uploader) {
            return Document::create([
                'work_order_id' => $wo->id,
                'title' => $title,
                'type' => $type,
                'file_path' => 'documents/work_orders/' . \Illuminate\Support\Str::slug($title) . '.pdf',
                'file_size' => random_int(150_000, 3_200_000),
                'uploaded_by' => $uploader->id,
            ]);
        };

        // WO1 (Offer 1, PST): full pipeline -> SELESAI, laporan terbit & terkirim
        $wo1 = $startWorkOrder($off1, $off1->offer_date, $supervisor, [
            'sla_date' => $off1->offer_date->copy()->addDays(10),
            'completed_at' => $off1->offer_date->copy()->addDays(22),
            'current_status' => 'SELESAI',
        ]);
        WorkOrderAssignment::create(['work_order_id' => $wo1->id, 'user_id' => $surveyor1->id, 'role_type' => 'surveyor']);
        WorkOrderAssignment::create(['work_order_id' => $wo1->id, 'user_id' => $reviewer1->id, 'role_type' => 'reviewer']);
        $advanceStatus($wo1, 'PERSIAPAN', 'SURVEY', $supervisor, $off1->offer_date->copy()->addDays(2), 'Surveyor ditugaskan ke lapangan');
        $advanceStatus($wo1, 'SURVEY', 'PENGERJAAN', $surveyor1, $off1->offer_date->copy()->addDays(6), 'Survey selesai, masuk pengolahan data');
        $advanceStatus($wo1, 'PENGERJAAN', 'REVIEW', $reviewer1, $off1->offer_date->copy()->addDays(14), 'Draft laporan diserahkan ke reviewer');
        $advanceStatus($wo1, 'REVIEW', 'CETAK', $reviewer1, $off1->offer_date->copy()->addDays(20), 'Laporan disetujui, siap cetak');
        $advanceStatus($wo1, 'CETAK', 'SELESAI', $supervisor, $off1->offer_date->copy()->addDays(22), 'Laporan final diserahkan ke klien');
        $asset1a = $addAsset($wo1, 'tanah_bangunan', 'Jl. Industri Raya No. 21, Pulogadung', 'Jakarta Timur', 'DKI Jakarta', 'Ruko 3 lantai + gudang');
        $asset1b = $addAsset($wo1, 'mesin_peralatan', 'Jl. Industri Raya No. 21, Pulogadung', 'Jakarta Timur', 'DKI Jakarta', 'Mesin produksi garmen 4 unit');
        $report1 = Report::create([
            'work_order_id' => $wo1->id,
            'report_no' => $wo1->contract_no,
            'report_date' => $off1->offer_date->copy()->addDays(18),
            'purpose' => 'Penjaminan Utang Bank',
            'resume_value' => 1850000000,
            'report_value' => 1900000000,
            'print_date' => $off1->offer_date->copy()->addDays(21),
            'final_at' => $wo1->completed_at,
            'created_by' => $reviewer1->id,
        ]);
        $report1->assets()->sync([$asset1a->id, $asset1b->id]);
        Delivery::create([
            'report_id' => $report1->id,
            'sent_date' => $off1->offer_date->copy()->addDays(22),
            'courier' => 'JNE',
            'tracking_no' => 'JNE-' . strtoupper(uniqid()),
            'received_date' => $off1->offer_date->copy()->addDays(24),
            'recipient_name' => 'Bagian Legal Bank Mandiri',
            'note' => 'Diterima lengkap oleh bagian legal',
        ]);
        $addDocument($wo1, 'Surat Penawaran & Kontrak - ' . $wo1->contract_no, 'penawaran', $opJkt);
        $addDocument($wo1, 'Scan Laporan Final - ' . $wo1->contract_no, 'scan_final', $reviewer1);

        // WO2 (Offer 2, JKT): full pipeline -> SELESAI, laporan terbit & terkirim
        $wo2 = $startWorkOrder($off2, $off2->offer_date, $opJkt, [
            'sla_date' => $off2->offer_date->copy()->addDays(10),
            'completed_at' => $off2->offer_date->copy()->addDays(20),
            'current_status' => 'SELESAI',
        ]);
        WorkOrderAssignment::create(['work_order_id' => $wo2->id, 'user_id' => $surveyor2->id, 'role_type' => 'surveyor']);
        WorkOrderAssignment::create(['work_order_id' => $wo2->id, 'user_id' => $reviewer2->id, 'role_type' => 'reviewer']);
        $advanceStatus($wo2, 'PERSIAPAN', 'SURVEY', $opJkt, $off2->offer_date->copy()->addDays(1), 'Surveyor ditugaskan ke lapangan');
        $advanceStatus($wo2, 'SURVEY', 'PENGERJAAN', $surveyor2, $off2->offer_date->copy()->addDays(5), 'Survey selesai, masuk pengolahan data');
        $advanceStatus($wo2, 'PENGERJAAN', 'REVIEW', $reviewer2, $off2->offer_date->copy()->addDays(12), 'Draft laporan diserahkan ke reviewer');
        $advanceStatus($wo2, 'REVIEW', 'CETAK', $reviewer2, $off2->offer_date->copy()->addDays(17), 'Laporan disetujui, siap cetak');
        $advanceStatus($wo2, 'CETAK', 'SELESAI', $opJkt, $off2->offer_date->copy()->addDays(20), 'Laporan final diserahkan ke klien');
        $asset2a = $addAsset($wo2, 'tanah_kosong', 'Jl. Boulevard Raya Blok M3', 'Jakarta Utara', 'DKI Jakarta', 'Tanah kosong siap bangun 1.200 m2');
        $asset2b = $addAsset($wo2, 'kendaraan', 'Jl. Boulevard Raya Blok M3', 'Jakarta Utara', 'DKI Jakarta', '3 unit truk operasional');
        $report2 = Report::create([
            'work_order_id' => $wo2->id,
            'report_no' => $wo2->contract_no,
            'report_date' => $off2->offer_date->copy()->addDays(16),
            'purpose' => 'Jaminan Kredit Investasi',
            'resume_value' => 2400000000,
            'report_value' => 2450000000,
            'print_date' => $off2->offer_date->copy()->addDays(19),
            'final_at' => $wo2->completed_at,
            'created_by' => $reviewer2->id,
        ]);
        $report2->assets()->sync([$asset2a->id, $asset2b->id]);
        Delivery::create([
            'report_id' => $report2->id,
            'sent_date' => $off2->offer_date->copy()->addDays(20),
            'courier' => 'TIKI',
            'tracking_no' => 'TIKI-' . strtoupper(uniqid()),
            'received_date' => $off2->offer_date->copy()->addDays(23),
            'recipient_name' => 'Bagian Legal Bank BCA',
            'note' => null,
        ]);
        $addDocument($wo2, 'Surat Penawaran & Kontrak - ' . $wo2->contract_no, 'penawaran', $opJkt);
        $addDocument($wo2, 'Scan Laporan Final - ' . $wo2->contract_no, 'scan_final', $reviewer2);

        // WO3 (Offer 3, SUB): dibatalkan sebelum pekerjaan berjalan
        $wo3 = $startWorkOrder($off3, $off3->offer_date, $sysAdmin, [
            'sla_date' => $off3->offer_date->copy()->addDays(10),
            'current_status' => 'BATAL',
        ]);
        $advanceStatus($wo3, 'PERSIAPAN', 'BATAL', $sysAdmin, $off3->offer_date->copy()->addDays(5), 'Klien membatalkan kontrak karena dokumen legal debitur tidak lengkap');

        // WO4 (Offer 7, JKT): laporan sudah dicetak, belum dikirim
        $wo4 = $startWorkOrder($off7, $off7->offer_date, $opJkt, [
            'sla_date' => $off7->offer_date->copy()->addDays(12),
            'current_status' => 'CETAK',
        ]);
        WorkOrderAssignment::create(['work_order_id' => $wo4->id, 'user_id' => $surveyor1->id, 'role_type' => 'surveyor']);
        WorkOrderAssignment::create(['work_order_id' => $wo4->id, 'user_id' => $reviewer2->id, 'role_type' => 'reviewer']);
        $advanceStatus($wo4, 'PERSIAPAN', 'SURVEY', $opJkt, $off7->offer_date->copy()->addDays(2), 'Surveyor ditugaskan ke lapangan');
        $advanceStatus($wo4, 'SURVEY', 'PENGERJAAN', $surveyor1, $off7->offer_date->copy()->addDays(6), 'Survey selesai, masuk pengolahan data');
        $advanceStatus($wo4, 'PENGERJAAN', 'REVIEW', $reviewer2, $off7->offer_date->copy()->addDays(11), 'Draft laporan diserahkan ke reviewer');
        $advanceStatus($wo4, 'REVIEW', 'CETAK', $reviewer2, Carbon::now()->subDays(2), 'Laporan disetujui, siap cetak');
        $asset4a = $addAsset($wo4, 'tanah_bangunan', 'Jl. Sudirman Kav. 15', 'Jakarta Selatan', 'DKI Jakarta', 'Gedung kantor 5 lantai');
        $asset4b = $addAsset($wo4, 'inventaris', 'Jl. Sudirman Kav. 15', 'Jakarta Selatan', 'DKI Jakarta', 'Perabot & peralatan kantor');
        $report4 = Report::create([
            'work_order_id' => $wo4->id,
            'report_no' => $wo4->contract_no,
            'report_date' => $off7->offer_date->copy()->addDays(14),
            'purpose' => 'Penjaminan Utang Bank',
            'resume_value' => 3100000000,
            'report_value' => 3150000000,
            'print_date' => Carbon::now()->subDays(2),
            'final_at' => null,
            'created_by' => $reviewer2->id,
        ]);
        $report4->assets()->sync([$asset4a->id, $asset4b->id]);
        $addDocument($wo4, 'Surat Penawaran & Kontrak - ' . $wo4->contract_no, 'penawaran', $opJkt);
        $addDocument($wo4, 'Draft Laporan - ' . $wo4->contract_no, 'draft_laporan', $reviewer2);

        // WO5 (Offer 9, BDG): sedang REVIEW, laporan belum diterbitkan
        $wo5 = $startWorkOrder($off9, $off9->offer_date, $opBdg, [
            'sla_date' => $off9->offer_date->copy()->addDays(14),
            'current_status' => 'REVIEW',
        ]);
        WorkOrderAssignment::create(['work_order_id' => $wo5->id, 'user_id' => $surveyor2->id, 'role_type' => 'surveyor']);
        WorkOrderAssignment::create(['work_order_id' => $wo5->id, 'user_id' => $reviewer1->id, 'role_type' => 'reviewer']);
        $advanceStatus($wo5, 'PERSIAPAN', 'SURVEY', $opBdg, $off9->offer_date->copy()->addDays(2), 'Surveyor ditugaskan ke lapangan');
        $advanceStatus($wo5, 'SURVEY', 'PENGERJAAN', $surveyor2, $off9->offer_date->copy()->addDays(9), 'Survey selesai, masuk pengolahan data');
        $advanceStatus($wo5, 'PENGERJAAN', 'REVIEW', $reviewer1, $off9->offer_date->copy()->addDays(17), 'Draft laporan diserahkan ke reviewer');
        $addAsset($wo5, 'tanah_bangunan', 'Jl. Ir. H. Djuanda No. 12', 'Bandung', 'Jawa Barat', 'Rumah tinggal 2 lantai');
        $addDocument($wo5, 'Foto & Data Survey Lapangan - ' . $wo5->contract_no, 'survey', $surveyor2);

        // WO6 (Offer 11, PST): PENGERJAAN, SLA overdue (demo red-flag)
        $wo6 = $startWorkOrder($off11, $off11->offer_date, $supervisor, [
            'sla_date' => Carbon::now()->subDays(2),
            'current_status' => 'PENGERJAAN',
        ]);
        WorkOrderAssignment::create(['work_order_id' => $wo6->id, 'user_id' => $surveyor1->id, 'role_type' => 'surveyor']);
        WorkOrderAssignment::create(['work_order_id' => $wo6->id, 'user_id' => $reviewer1->id, 'role_type' => 'reviewer']);
        $advanceStatus($wo6, 'PERSIAPAN', 'SURVEY', $supervisor, $off11->offer_date->copy()->addDays(2), 'Surveyor ditugaskan ke lapangan');
        $advanceStatus($wo6, 'SURVEY', 'PENGERJAAN', $surveyor1, $off11->offer_date->copy()->addDays(9), 'Survey selesai, masuk pengolahan data');
        $addAsset($wo6, 'mesin_peralatan', 'Kawasan BSD City', 'Tangerang Selatan', 'Banten', 'Mesin pengolahan makanan 2 unit');

        // WO7 (Offer 12, JKT): baru masuk tahap SURVEY
        $wo7 = $startWorkOrder($off12, $off12->offer_date, $opJkt, [
            'sla_date' => $off12->offer_date->copy()->addDays(14),
            'current_status' => 'SURVEY',
        ]);
        WorkOrderAssignment::create(['work_order_id' => $wo7->id, 'user_id' => $surveyor2->id, 'role_type' => 'surveyor']);
        $advanceStatus($wo7, 'PERSIAPAN', 'SURVEY', $opJkt, $off12->offer_date->copy()->addDays(2), 'Surveyor ditugaskan ke lapangan');
        $addAsset($wo7, 'tanah_kosong', 'Jl. Gatot Subroto No. 88', 'Medan', 'Sumatera Utara', 'Tanah kosong 800 m2');

        // WO8 (Offer 14, BDG): baru dibuat, masih PERSIAPAN
        $startWorkOrder($off14, $off14->offer_date, $opBdg, [
            'sla_date' => $off14->offer_date->copy()->addDays(14),
        ]);

        $this->call(RolePermissionSeeder::class);
    }
}
