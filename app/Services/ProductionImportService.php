<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Debtor;
use App\Models\Delivery;
use App\Models\ImportStaging;
use App\Models\Offer;
use App\Models\Organization;
use App\Models\Report;
use App\Models\StatusHistory;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductionImportService
{
    public function parseCsvAndStage(UploadedFile $file, string $defaultBranchCode, int $userId): string
    {
        $batchId = (string) Str::uuid();
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new \Exception('Gagal membuka file unggahan.');
        }

        // Read header
        $header = fgetcsv($handle, 0, ',');

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $offerNo = trim($row[0] ?? '');
            if (!$offerNo) {
                continue;
            }

            ImportStaging::create([
                'batch_id' => $batchId,
                'offer_no' => $offerNo,
                'contract_date' => $this->parseDate($row[1] ?? null),
                'branch_code' => trim($row[2] ?? '') ?: $defaultBranchCode,
                'debtor_name' => trim($row[3] ?? ''),
                'client_name' => trim($row[4] ?? ''),
                'report_user_name' => trim($row[5] ?? ''),
                'fee' => $this->parseAmount($row[6] ?? 0),
                'ta' => $this->parseAmount($row[7] ?? 0),
                'status' => strtoupper(trim($row[8] ?? 'SELESAI')),
                'report_no' => trim($row[9] ?? '') ?: null,
                'report_date' => $this->parseDate($row[10] ?? null),
                'resume_value' => $this->parseAmount($row[11] ?? 0),
                'report_value' => $this->parseAmount($row[12] ?? 0),
                'sent_date' => $this->parseDate($row[13] ?? null),
                'courier' => trim($row[14] ?? '') ?: null,
                'tracking_no' => trim($row[15] ?? '') ?: null,
                'received_date' => $this->parseDate($row[16] ?? null),
                'recipient_name' => trim($row[17] ?? '') ?: null,
                'is_processed' => false,
            ]);
        }

        fclose($handle);

        return $batchId;
    }

    public function processStagingBatch(string $batchId, int $userId): array
    {
        $stagings = ImportStaging::where('batch_id', $batchId)
            ->where('is_processed', false)
            ->get();

        $successCount = 0;
        $errorCount = 0;

        foreach ($stagings as $stg) {
            try {
                // 1. Branch
                $branchCode = strtoupper($stg->branch_code ?: 'PST');
                $branch = Branch::firstOrCreate(
                    ['code' => $branchCode],
                    ['name' => "Cabang {$branchCode}", 'active' => true]
                );

                // 2. Debtor
                $debtorName = $stg->debtor_name ?: 'Debitur Tanpa Nama';
                $debtor = Debtor::firstOrCreate(
                    ['name' => $debtorName],
                    ['identifier' => 'DEB-' . date('Y') . '-' . rand(1000, 9999), 'address' => '-']
                );

                // 3. Client Organization
                $clientName = $stg->client_name ?: 'Pemberi Tugas Umum';
                $client = Organization::firstOrCreate(
                    ['name' => $clientName, 'type' => 'pemberi_tugas'],
                    ['address' => '-']
                );

                // 4. Report User Organization
                $reportUser = null;
                if ($stg->report_user_name) {
                    $reportUser = Organization::firstOrCreate(
                        ['name' => $stg->report_user_name, 'type' => 'pengguna_laporan'],
                        ['address' => '-']
                    );
                }

                // Tax calculations
                $fee = (float) $stg->fee;
                $ta = (float) $stg->ta;
                $dpp = max(0, $fee - $ta);
                $ppn = round($dpp * 0.11, 2);
                $pph = round($dpp * 0.02, 2);

                // 5. Offer
                $offer = Offer::updateOrCreate(
                    ['offer_no' => $stg->offer_no],
                    [
                        'offer_date' => $stg->contract_date ?: Carbon::today(),
                        'branch_id' => $branch->id,
                        'debtor_id' => $debtor->id,
                        'client_id' => $client->id,
                        'report_user_id' => $reportUser?->id,
                        'fee' => $fee,
                        'ta' => $ta,
                        'dpp' => $dpp,
                        'ppn' => $ppn,
                        'pph' => $pph,
                        'outcome' => 'DITERIMA',
                        'created_by' => $userId,
                    ]
                );

                // 6. WorkOrder
                $status = in_array($stg->status, ['PERSIAPAN', 'SURVEY', 'PENGERJAAN', 'REVIEW', 'CETAK', 'SELESAI', 'BATAL']) 
                    ? $stg->status 
                    : 'SELESAI';

                $workOrder = WorkOrder::updateOrCreate(
                    ['offer_id' => $offer->id],
                    [
                        'contract_no' => $offer->offer_no,
                        'contract_date' => $stg->contract_date ?: Carbon::today(),
                        'survey_required' => true,
                        'sla_date' => $stg->contract_date ? Carbon::parse($stg->contract_date)->addDays(14) : Carbon::today()->addDays(14),
                        'current_status' => $status,
                        'started_at' => $stg->contract_date ?: Carbon::today(),
                        'completed_at' => $status === 'SELESAI' ? ($stg->report_date ?: Carbon::now()) : null,
                    ]
                );

                StatusHistory::create([
                    'work_order_id' => $workOrder->id,
                    'from_status' => null,
                    'to_status' => $status,
                    'changed_by' => $userId,
                    'note' => "Impor data historis dari batch {$batchId}",
                ]);

                // 7. Report
                if ($stg->report_no) {
                    $report = Report::updateOrCreate(
                        ['report_no' => $stg->report_no],
                        [
                            'work_order_id' => $workOrder->id,
                            'report_date' => $stg->report_date ?: Carbon::today(),
                            'purpose' => 'Penjaminan Utang / Impor Historis',
                            'resume_value' => $stg->resume_value,
                            'report_value' => $stg->report_value,
                            'print_date' => $stg->report_date ?: Carbon::today(),
                            'created_by' => $userId,
                        ]
                    );

                    // 8. Delivery
                    if ($stg->courier || $stg->tracking_no) {
                        Delivery::updateOrCreate(
                            ['report_id' => $report->id],
                            [
                                'sent_date' => $stg->sent_date ?: Carbon::today(),
                                'courier' => $stg->courier ?: 'Hand Delivery',
                                'tracking_no' => $stg->tracking_no,
                                'received_date' => $stg->received_date,
                                'recipient_name' => $stg->recipient_name,
                            ]
                        );
                    }
                }

                $stg->update(['is_processed' => true, 'error_message' => null]);
                $successCount++;
            } catch (\Exception $e) {
                $stg->update(['error_message' => $e->getMessage()]);
                $errorCount++;
            }
        }

        return [
            'success' => $successCount,
            'failed' => $errorCount,
        ];
    }

    public function downloadTemplateCsv(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Template_Impor_Produksi_KJPP.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

            fputcsv($file, [
                'No. Penawaran',
                'Tanggal Kontrak (YYYY-MM-DD)',
                'Kode Cabang (PST/JKT/SUB)',
                'Nama Debitur',
                'Pemberi Tugas (Klien)',
                'Pengguna Laporan',
                'Fee Total (Rp)',
                'TA (Rp)',
                'Status Pekerjaan (SELESAI/PENGERJAAN/SURVEY/REVIEW)',
                'No. Laporan Resmi',
                'Tanggal Laporan (YYYY-MM-DD)',
                'Nilai Resume (Rp)',
                'Nilai Laporan (Rp)',
                'Tanggal Kirim (YYYY-MM-DD)',
                'Kurir',
                'No. Resi',
                'Tanggal Diterima (YYYY-MM-DD)',
                'Nama Penerima',
            ]);

            fputcsv($file, [
                'PNW/2026/9001',
                '2026-01-15',
                'PST',
                'PT Sentosa Utama Jaya',
                'PT Bank Central Asia Tbk',
                'Divisi Credit Risk BCA',
                '25000000',
                '2000000',
                'SELESAI',
                'LAP/2026/9001',
                '2026-01-25',
                '12500000000',
                '12450000000',
                '2026-01-26',
                'JNE',
                'JNE123456789',
                '2026-01-27',
                'Bpk. Agus BCA',
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function parseAmount(?string $val): float
    {
        if (!$val) return 0;
        $val = preg_replace('/[^0-9.]/', '', str_replace(',', '.', $val));
        return (float) $val;
    }

    private function parseDate(?string $val): ?string
    {
        if (!$val) return null;
        try {
            return Carbon::parse(trim($val))->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
