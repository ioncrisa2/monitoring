<?php

namespace App\Services;

use App\Models\WorkOrder;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductionExportService
{
    /**
     * 5 kelompok kolom: nama grup => daftar [label, tipe]. Tipe: text, currency, integer, date, bool.
     */
    private const COLUMN_GROUPS = [
        'IDENTITAS' => [
            ['No. Kontrak / Penawaran', 'text'],
            ['Tanggal Kontrak', 'date'],
            ['Cabang', 'text'],
            ['Nama Debitur', 'text'],
            ['Pemberi Tugas (Klien)', 'text'],
            ['Pengguna Laporan', 'text'],
        ],
        'KEUANGAN' => [
            ['Fee Total (Rp)', 'currency'],
            ['TA (Rp)', 'currency'],
            ['DPP (Rp)', 'currency'],
            ['PPN 11% (Rp)', 'currency'],
            ['PPh 2% (Rp)', 'currency'],
        ],
        'OPERASIONAL & SLA' => [
            ['Flag Survey', 'bool'],
            ['Deadline SLA', 'date'],
            ['Status Saat Ini', 'text'],
            ['PIC Surveyor', 'text'],
            ['PIC Reviewer', 'text'],
            ['Aging (Hari)', 'integer'],
            ['Overdue SLA', 'bool'],
        ],
        'LAPORAN RESMI' => [
            ['No. Laporan Resmi', 'text'],
            ['Tanggal Laporan', 'date'],
            ['Tujuan Penilaian', 'text'],
            ['Nilai Resume (Rp)', 'currency'],
            ['Nilai Laporan (Rp)', 'currency'],
            ['Tanggal Cetak', 'date'],
        ],
        'PENGIRIMAN' => [
            ['Tanggal Kirim', 'date'],
            ['Kurir / Ekspedisi', 'text'],
            ['No. Resi Tracking', 'text'],
            ['Tanggal Diterima', 'date'],
            ['Nama Penerima', 'text'],
        ],
    ];

    private const GROUP_COLORS = [
        'IDENTITAS' => '4338CA',
        'KEUANGAN' => '047857',
        'OPERASIONAL & SLA' => 'B45309',
        'LAPORAN RESMI' => '1D4ED8',
        'PENGIRIMAN' => 'BE185D',
    ];

    public function exportXlsx(?int $branchId = null, ?string $fromDate = null, ?string $toDate = null, ?string $status = null): StreamedResponse
    {
        $filename = 'Laporan_Produksi_KJPP_' . Carbon::now()->format('Ymd_His') . '.xlsx';

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
            $query->whereHas('offer', fn ($q) => $q->where('branch_id', $branchId));
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

        $spreadsheet = $this->buildSpreadsheet($workOrders);

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        };

        return response()->stream($callback, 200, $headers);
    }

    private function buildSpreadsheet($workOrders): Spreadsheet
    {
        $columns = [];
        foreach (self::COLUMN_GROUPS as $group => $cols) {
            foreach ($cols as [$label, $type]) {
                $columns[] = ['group' => $group, 'label' => $label, 'type' => $type];
            }
        }
        $totalColumns = count($columns);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Produksi');

        $lastColLetter = Coordinate::stringFromColumnIndex($totalColumns);

        // Baris judul
        $sheet->setCellValue('A1', 'LAPORAN PRODUKSI KJPP');
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sheet->setCellValue('A2', 'Dicetak: ' . Carbon::now()->format('d-m-Y H:i') . ' WIB | Total baris: ' . $workOrders->count());
        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('6B7280');

        // Baris 4: super-header per kelompok (merge sesuai lebar kelompok)
        // Baris 5: header kolom
        $groupRow = 4;
        $headerRow = 5;
        $colIndex = 1;

        foreach (self::COLUMN_GROUPS as $group => $cols) {
            $startLetter = Coordinate::stringFromColumnIndex($colIndex);
            $endLetter = Coordinate::stringFromColumnIndex($colIndex + count($cols) - 1);

            $sheet->setCellValue("{$startLetter}{$groupRow}", $group);
            if ($startLetter !== $endLetter) {
                $sheet->mergeCells("{$startLetter}{$groupRow}:{$endLetter}{$groupRow}");
            }

            $groupStyle = $sheet->getStyle("{$startLetter}{$groupRow}:{$endLetter}{$groupRow}");
            $groupStyle->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
            $groupStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GROUP_COLORS[$group]);
            $groupStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            foreach ($cols as $i => [$label, $type]) {
                $letter = Coordinate::stringFromColumnIndex($colIndex + $i);
                $sheet->setCellValue("{$letter}{$headerRow}", $label);
            }

            $colIndex += count($cols);
        }

        $headerStyle = $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}");
        $headerStyle->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1F2937'));
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        // Data rows
        $rowIndex = $headerRow + 1;
        $currencyFormat = '#,##0.00';
        $dateFormat = 'dd-mm-yyyy';

        foreach ($workOrders as $wo) {
            $reports = $wo->reports;

            $baseValues = [
                $wo->contract_no,
                $wo->contract_date,
                $wo->offer?->branch?->name ?? '',
                $wo->offer?->debtor?->name ?? '',
                $wo->offer?->client?->name ?? '',
                $wo->offer?->reportUser?->name ?? $wo->offer?->client?->name ?? '',

                (float) ($wo->offer?->fee ?? 0),
                (float) ($wo->offer?->ta ?? 0),
                (float) ($wo->offer?->dpp ?? 0),
                (float) ($wo->offer?->ppn ?? 0),
                (float) ($wo->offer?->pph ?? 0),

                $wo->survey_required,
                $wo->sla_date,
                $wo->current_status,
                $wo->surveyors->first()?->user?->name ?? '-',
                $wo->reviewers->first()?->user?->name ?? '-',
                $wo->aging_days,
                $wo->is_overdue,
            ];

            if ($reports->isEmpty()) {
                $this->writeRow($sheet, $columns, $rowIndex, array_merge($baseValues, [
                    '-', null, '', 0, 0, null,
                    null, '', '', null, '',
                ]));
                $rowIndex++;
            } else {
                foreach ($reports as $r) {
                    $del = $r->delivery;
                    $this->writeRow($sheet, $columns, $rowIndex, array_merge($baseValues, [
                        $r->report_no,
                        $r->report_date,
                        $r->purpose,
                        (float) ($r->resume_value ?? 0),
                        (float) ($r->report_value ?? 0),
                        $r->print_date,

                        $del?->sent_date,
                        $del?->courier ?? '',
                        $del?->tracking_no ?? '',
                        $del?->received_date,
                        $del?->recipient_name ?? '',
                    ]));
                    $rowIndex++;
                }
            }
        }

        $lastDataRow = max($rowIndex - 1, $headerRow);

        // Format per kolom (currency, date) + border + zebra striping
        foreach ($columns as $i => $col) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            if ($col['type'] === 'currency') {
                $sheet->getStyle("{$letter}" . ($headerRow + 1) . ":{$letter}{$lastDataRow}")->getNumberFormat()->setFormatCode($currencyFormat);
            } elseif ($col['type'] === 'date') {
                $sheet->getStyle("{$letter}" . ($headerRow + 1) . ":{$letter}{$lastDataRow}")->getNumberFormat()->setFormatCode($dateFormat);
            }
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        $dataRange = "A" . ($headerRow + 1) . ":{$lastColLetter}{$lastDataRow}";
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');

        for ($r = $headerRow + 1; $r <= $lastDataRow; $r++) {
            if (($r - $headerRow) % 2 === 0) {
                $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9FAFB');
            }
        }

        $sheet->setAutoFilter("A{$headerRow}:{$lastColLetter}{$headerRow}");
        $sheet->freezePane("A" . ($headerRow + 1));

        return $spreadsheet;
    }

    private function writeRow($sheet, array $columns, int $rowIndex, array $values): void
    {
        foreach ($values as $i => $value) {
            $type = $columns[$i]['type'];
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $cell = "{$letter}{$rowIndex}";

            if ($type === 'date') {
                if ($value) {
                    $sheet->setCellValue($cell, ExcelDate::PHPToExcel($value instanceof Carbon ? $value : Carbon::parse($value)));
                } else {
                    $sheet->setCellValue($cell, '');
                }
            } elseif ($type === 'bool') {
                $sheet->setCellValue($cell, $value === null ? '' : ($value ? 'YA' : 'TIDAK'));
            } else {
                $sheet->setCellValue($cell, $value ?? '');
            }
        }
    }
}
