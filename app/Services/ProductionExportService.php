<?php

namespace App\Services;

use App\Models\WorkOrder;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductionExportService
{
    public function exportCsv(?int $branchId = null, ?string $fromDate = null, ?string $toDate = null, ?string $status = null): StreamedResponse
    {
        $filename = 'Laporan_Produksi_KJPP_' . Carbon::now()->format('Ymd_His') . '.csv';

        $query = WorkOrder::with([
            'offer.branch',
            'offer.debtor',
            'offer.client',
            'offer.reportUser',
            'surveyors.user',
            'reviewers.user',
            'reports.delivery',
        ]);

        if ($branchId) {
            $query->whereHas('offer', fn($q) => $q->where('branch_id', $branchId));
        }

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        if ($status) {
            $query->where('current_status', $status);
        }

        $workOrders = $query->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($workOrders) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Microsoft Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // 5 Column Groups Header
            fputcsv($file, [
                // 1. IDENTITAS
                'No. Kontrak / Penawaran',
                'Tanggal Kontrak',
                'Cabang',
                'Nama Debitur',
                'Pemberi Tugas (Klien)',
                'Pengguna Laporan',

                // 2. KEUANGAN
                'Fee Total (Rp)',
                'TA (Rp)',
                'DPP (Rp)',
                'PPN 11% (Rp)',
                'PPh 2% (Rp)',

                // 3. OPERASIONAL & SLA
                'Flag Survey',
                'Deadline SLA',
                'Status Saat Ini',
                'PIC Surveyor',
                'PIC Reviewer',
                'Aging (Hari)',
                'Overdue SLA',

                // 4. LAPORAN RESMI
                'No. Laporan Resmi',
                'Tanggal Laporan',
                'Tujuan Penilaian',
                'Nilai Resume (Rp)',
                'Nilai Laporan (Rp)',
                'Tanggal Cetak',

                // 5. PENGIRIMAN
                'Tanggal Kirim',
                'Kurir / Ekspedisi',
                'No. Resi Tracking',
                'Tanggal Diterima',
                'Nama Penerima',
            ]);

            foreach ($workOrders as $wo) {
                $reports = $wo->reports;
                $repCount = $reports->count();

                if ($repCount > 0) {
                    foreach ($reports as $r) {
                        $del = $r->delivery;
                        fputcsv($file, [
                            $wo->contract_no,
                            $wo->contract_date ? $wo->contract_date->format('Y-m-d') : '',
                            $wo->offer?->branch?->name ?? '',
                            $wo->offer?->debtor?->name ?? '',
                            $wo->offer?->client?->name ?? '',
                            $wo->offer?->reportUser?->name ?? $wo->offer?->client?->name ?? '',

                            $wo->offer?->fee ?? 0,
                            $wo->offer?->ta ?? 0,
                            $wo->offer?->dpp ?? 0,
                            $wo->offer?->ppn ?? 0,
                            $wo->offer?->pph ?? 0,

                            $wo->survey_required ? 'YA' : 'TIDAK',
                            $wo->sla_date ? $wo->sla_date->format('Y-m-d') : '',
                            $wo->current_status,
                            $wo->surveyors->first()?->user?->name ?? '-',
                            $wo->reviewers->first()?->user?->name ?? '-',
                            $wo->aging_days,
                            $wo->is_overdue ? 'YA' : 'TIDAK',

                            $r->report_no,
                            $r->report_date ? $r->report_date->format('Y-m-d') : '',
                            $r->purpose,
                            $r->resume_value ?? 0,
                            $r->report_value ?? 0,
                            $r->print_date ? $r->print_date->format('Y-m-d') : '',

                            $del?->sent_date ? $del->sent_date->format('Y-m-d') : '',
                            $del?->courier ?? '',
                            $del?->tracking_no ?? '',
                            $del?->received_date ? $del->received_date->format('Y-m-d') : '',
                            $del?->recipient_name ?? '',
                        ]);
                    }
                } else {
                    fputcsv($file, [
                        $wo->contract_no,
                        $wo->contract_date ? $wo->contract_date->format('Y-m-d') : '',
                        $wo->offer?->branch?->name ?? '',
                        $wo->offer?->debtor?->name ?? '',
                        $wo->offer?->client?->name ?? '',
                        $wo->offer?->reportUser?->name ?? $wo->offer?->client?->name ?? '',

                        $wo->offer?->fee ?? 0,
                        $wo->offer?->ta ?? 0,
                        $wo->offer?->dpp ?? 0,
                        $wo->offer?->ppn ?? 0,
                        $wo->offer?->pph ?? 0,

                        $wo->survey_required ? 'YA' : 'TIDAK',
                        $wo->sla_date ? $wo->sla_date->format('Y-m-d') : '',
                        $wo->current_status,
                        $wo->surveyors->first()?->user?->name ?? '-',
                        $wo->reviewers->first()?->user?->name ?? '-',
                        $wo->aging_days,
                        $wo->is_overdue ? 'YA' : 'TIDAK',

                        '-', '', '', 0, 0, '',
                        '', '', '', '', ''
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
