<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Debtor;
use App\Models\Offer;
use App\Models\Organization;
use App\Models\StatusHistory;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAssignment;
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
        // 1. Seed Branches
        $pst = Branch::create(['code' => 'PST', 'name' => 'Kantor Pusat Jakarta', 'active' => true]);
        $jkt = Branch::create(['code' => 'JKT', 'name' => 'Cabang Jakarta Selatan', 'active' => true]);
        $sub = Branch::create(['code' => 'SUB', 'name' => 'Cabang Surabaya', 'active' => true]);

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

        $surveyor1 = User::create([
            'branch_id' => $jkt->id,
            'name' => 'Andi Surveyor',
            'email' => 'andi.surveyor@kjpp.com',
            'password' => Hash::make('password'),
            'role' => 'surveyor',
            'phone' => '081234567893',
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

        $penggunaMandiri = Organization::create([
            'name' => 'Divisi Credit Risk & Special Asset Management',
            'type' => 'pengguna_laporan',
            'address' => 'Plaza Mandiri Lantai 12, Jakarta',
            'tax_id' => '01.000.000.0-000.000',
            'phone' => '021-5265001',
        ]);

        // 4. Seed Debtors
        $deb1 = Debtor::create([
            'name' => 'PT Surya Citra Kencana',
            'identifier' => 'DEB-2026-001',
            'address' => 'Kawasan Industri Pulogadung, Jakarta Timur',
        ]);

        $deb2 = Debtor::create([
            'name' => 'PT Nusantara Bangun Jaya',
            'identifier' => 'DEB-2026-002',
            'address' => 'Jl. Boulevard Raya Blok M3, Kelapa Gading, Jakarta Utara',
        ]);

        $deb3 = Debtor::create([
            'name' => 'CV Agung Utama Mandiri',
            'identifier' => 'DEB-2026-003',
            'address' => 'Jl. Pemuda No. 45, Surabaya',
        ]);

        $deb4 = Debtor::create([
            'name' => 'PT Wisma Asri Realty',
            'identifier' => 'DEB-2026-004',
            'address' => 'Kawasan BSD City, Tangerang Selatan',
        ]);

        // 5. Seed Offers & WorkOrders
        
        // Offer 1: DRAFT
        Offer::create([
            'offer_no' => 'PNW/2026/0001',
            'offer_date' => Carbon::now()->subDays(10),
            'branch_id' => $jkt->id,
            'debtor_id' => $deb1->id,
            'client_id' => $bankMandiri->id,
            'report_user_id' => $penggunaMandiri->id,
            'fee' => 15000000,
            'ta' => 2000000,
            'dpp' => 13000000,
            'ppn' => 1430000,
            'pph' => 260000,
            'outcome' => 'DRAFT',
            'created_by' => $opJkt->id,
        ]);

        // Offer 2: DIKIRIM
        Offer::create([
            'offer_no' => 'PNW/2026/0002',
            'offer_date' => Carbon::now()->subDays(8),
            'branch_id' => $jkt->id,
            'debtor_id' => $deb2->id,
            'client_id' => $bankBCA->id,
            'report_user_id' => null,
            'fee' => 25000000,
            'ta' => 3000000,
            'dpp' => 22000000,
            'ppn' => 2420000,
            'pph' => 440000,
            'outcome' => 'DIKIRIM',
            'created_by' => $opJkt->id,
        ]);

        // Offer 3: TIDAK_LANJUT
        Offer::create([
            'offer_no' => 'PNW/2026/0003',
            'offer_date' => Carbon::now()->subDays(15),
            'branch_id' => $sub->id,
            'debtor_id' => $deb3->id,
            'client_id' => $bankBRI->id,
            'report_user_id' => null,
            'fee' => 18000000,
            'ta' => 2000000,
            'dpp' => 16000000,
            'ppn' => 1760000,
            'pph' => 320000,
            'outcome' => 'TIDAK_LANJUT',
            'note' => 'Klien membatalkan rencana kredit',
            'created_by' => $sysAdmin->id,
        ]);

        // Offer 4: DITERIMA -> WorkOrder 1 (PERSIAPAN)
        $off4 = Offer::create([
            'offer_no' => 'PNW/2026/0004',
            'offer_date' => Carbon::now()->subDays(5),
            'branch_id' => $jkt->id,
            'debtor_id' => $deb4->id,
            'client_id' => $bankMandiri->id,
            'report_user_id' => $penggunaMandiri->id,
            'fee' => 35000000,
            'ta' => 5000000,
            'dpp' => 30000000,
            'ppn' => 3300000,
            'pph' => 600000,
            'outcome' => 'DITERIMA',
            'created_by' => $opJkt->id,
        ]);

        $wo1 = WorkOrder::create([
            'offer_id' => $off4->id,
            'contract_no' => $off4->offer_no,
            'contract_date' => Carbon::now()->subDays(5),
            'survey_required' => true,
            'sla_date' => Carbon::now()->addDays(5),
            'current_status' => 'PERSIAPAN',
            'started_at' => Carbon::now()->subDays(5),
        ]);

        StatusHistory::create([
            'work_order_id' => $wo1->id,
            'from_status' => null,
            'to_status' => 'PERSIAPAN',
            'changed_by' => $opJkt->id,
            'note' => 'Pekerjaan dibuat dari penawaran ' . $off4->offer_no,
        ]);

        // Offer 5: DITERIMA -> WorkOrder 2 (SURVEY)
        $off5 = Offer::create([
            'offer_no' => 'PNW/2026/0005',
            'offer_date' => Carbon::now()->subDays(7),
            'branch_id' => $jkt->id,
            'debtor_id' => $deb1->id,
            'client_id' => $bankBCA->id,
            'report_user_id' => null,
            'fee' => 42000000,
            'ta' => 4000000,
            'dpp' => 38000000,
            'ppn' => 4180000,
            'pph' => 760000,
            'outcome' => 'DITERIMA',
            'created_by' => $opJkt->id,
        ]);

        $wo2 = WorkOrder::create([
            'offer_id' => $off5->id,
            'contract_no' => $off5->offer_no,
            'contract_date' => Carbon::now()->subDays(7),
            'survey_required' => true,
            'sla_date' => Carbon::now()->addDays(3),
            'current_status' => 'SURVEY',
            'started_at' => Carbon::now()->subDays(7),
        ]);

        WorkOrderAssignment::create([
            'work_order_id' => $wo2->id,
            'user_id' => $surveyor1->id,
            'role_type' => 'surveyor',
        ]);

        StatusHistory::create(['work_order_id' => $wo2->id, 'from_status' => null, 'to_status' => 'PERSIAPAN', 'changed_by' => $opJkt->id]);
        StatusHistory::create(['work_order_id' => $wo2->id, 'from_status' => 'PERSIAPAN', 'to_status' => 'SURVEY', 'changed_by' => $opJkt->id, 'note' => 'Surveyor ditugaskan ke lapangan']);

        // Offer 6: DITERIMA -> WorkOrder 3 (PENGERJAAN & OVERDUE SLA 🔥)
        $off6 = Offer::create([
            'offer_no' => 'PNW/2026/0006',
            'offer_date' => Carbon::now()->subDays(14),
            'branch_id' => $jkt->id,
            'debtor_id' => $deb2->id,
            'client_id' => $bankBRI->id,
            'report_user_id' => null,
            'fee' => 50000000,
            'ta' => 5000000,
            'dpp' => 45000000,
            'ppn' => 4950000,
            'pph' => 900000,
            'outcome' => 'DITERIMA',
            'created_by' => $opJkt->id,
        ]);

        $wo3 = WorkOrder::create([
            'offer_id' => $off6->id,
            'contract_no' => $off6->offer_no,
            'contract_date' => Carbon::now()->subDays(14),
            'survey_required' => true,
            'sla_date' => Carbon::now()->subDays(2), // Overdue by 2 days!
            'current_status' => 'PENGERJAAN',
            'started_at' => Carbon::now()->subDays(14),
        ]);

        WorkOrderAssignment::create(['work_order_id' => $wo3->id, 'user_id' => $surveyor1->id, 'role_type' => 'surveyor']);
        WorkOrderAssignment::create(['work_order_id' => $wo3->id, 'user_id' => $reviewer1->id, 'role_type' => 'reviewer']);

        StatusHistory::create(['work_order_id' => $wo3->id, 'from_status' => null, 'to_status' => 'PERSIAPAN', 'changed_by' => $opJkt->id]);
        StatusHistory::create(['work_order_id' => $wo3->id, 'from_status' => 'PERSIAPAN', 'to_status' => 'SURVEY', 'changed_by' => $opJkt->id]);
        StatusHistory::create(['work_order_id' => $wo3->id, 'from_status' => 'SURVEY', 'to_status' => 'PENGERJAAN', 'changed_by' => $surveyor1->id, 'note' => 'Survey selesai, masuk pengolahan data']);

        // Offer 7: DITERIMA -> WorkOrder 4 (REVIEW)
        $off7 = Offer::create([
            'offer_no' => 'PNW/2026/0007',
            'offer_date' => Carbon::now()->subDays(12),
            'branch_id' => $pst->id,
            'debtor_id' => $deb4->id,
            'client_id' => $bankMandiri->id,
            'report_user_id' => $penggunaMandiri->id,
            'fee' => 60000000,
            'ta' => 6000000,
            'dpp' => 54000000,
            'ppn' => 5940000,
            'pph' => 1080000,
            'outcome' => 'DITERIMA',
            'created_by' => $supervisor->id,
        ]);

        $wo4 = WorkOrder::create([
            'offer_id' => $off7->id,
            'contract_no' => $off7->offer_no,
            'contract_date' => Carbon::now()->subDays(12),
            'survey_required' => true,
            'sla_date' => Carbon::now()->addDays(4),
            'current_status' => 'REVIEW',
            'started_at' => Carbon::now()->subDays(12),
        ]);

        WorkOrderAssignment::create(['work_order_id' => $wo4->id, 'user_id' => $reviewer1->id, 'role_type' => 'reviewer']);

        StatusHistory::create(['work_order_id' => $wo4->id, 'from_status' => null, 'to_status' => 'PERSIAPAN', 'changed_by' => $supervisor->id]);
        StatusHistory::create(['work_order_id' => $wo4->id, 'from_status' => 'PERSIAPAN', 'to_status' => 'PENGERJAAN', 'changed_by' => $supervisor->id]);
        StatusHistory::create(['work_order_id' => $wo4->id, 'from_status' => 'PENGERJAAN', 'to_status' => 'REVIEW', 'changed_by' => $reviewer1->id, 'note' => 'Draft laporan diserahkan ke reviewer']);

        // Offer 8: DITERIMA -> WorkOrder 5 (SELESAI)
        $off8 = Offer::create([
            'offer_no' => 'PNW/2026/0008',
            'offer_date' => Carbon::now()->subDays(20),
            'branch_id' => $pst->id,
            'debtor_id' => $deb3->id,
            'client_id' => $bankBCA->id,
            'report_user_id' => null,
            'fee' => 30000000,
            'ta' => 3000000,
            'dpp' => 27000000,
            'ppn' => 2970000,
            'pph' => 540000,
            'outcome' => 'DITERIMA',
            'created_by' => $supervisor->id,
        ]);

        $wo5 = WorkOrder::create([
            'offer_id' => $off8->id,
            'contract_no' => $off8->offer_no,
            'contract_date' => Carbon::now()->subDays(20),
            'survey_required' => true,
            'sla_date' => Carbon::now()->subDays(3),
            'current_status' => 'SELESAI',
            'started_at' => Carbon::now()->subDays(20),
            'completed_at' => Carbon::now()->subDays(2),
        ]);

        StatusHistory::create(['work_order_id' => $wo5->id, 'from_status' => null, 'to_status' => 'PERSIAPAN', 'changed_by' => $supervisor->id]);
        StatusHistory::create(['work_order_id' => $wo5->id, 'from_status' => 'PERSIAPAN', 'to_status' => 'SELESAI', 'changed_by' => $supervisor->id, 'note' => 'Laporan final diserahkan']);

        $this->call(RolePermissionSeeder::class);
    }
}
