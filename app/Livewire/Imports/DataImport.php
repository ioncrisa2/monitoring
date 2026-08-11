<?php

namespace App\Livewire\Imports;

use App\Models\Branch;
use App\Models\ImportStaging;
use App\Services\ProductionImportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class DataImport extends Component
{
    use WithFileUploads;

    public $upload_file;
    public string $default_branch_code = 'PST';
    public ?string $currentBatchId = null;

    public function downloadTemplate(ProductionImportService $importService)
    {
        return $importService->downloadTemplateCsv();
    }

    public function uploadFile(ProductionImportService $importService): void
    {
        $this->authorize('menu.imports');
        $this->validate([
            'upload_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB
            'default_branch_code' => 'required|string',
        ]);

        $batchId = $importService->parseCsvAndStage(
            $this->upload_file,
            $this->default_branch_code,
            Auth::id()
        );

        $this->currentBatchId = $batchId;
        session()->flash('message', 'Berkas CSV berhasil diparse dan disimpan ke staging table. Silakan periksa data preview di bawah sebelum memproses konversi.');
        $this->reset('upload_file');
    }

    public function processBatch(ProductionImportService $importService): void
    {
        $this->authorize('menu.imports');
        if (!$this->currentBatchId) {
            session()->flash('error', 'Tidak ada batch data staging yang dipilih.');
            return;
        }

        $results = $importService->processStagingBatch($this->currentBatchId, Auth::id());

        session()->flash('message', "Proses migrasi selesai. {$results['success']} data berhasil dikonversi ke tabel produksi utama, {$results['failed']} gagal.");
    }

    public function clearStaging(): void
    {
        $this->authorize('menu.imports');
        if ($this->currentBatchId) {
            ImportStaging::where('batch_id', $this->currentBatchId)->delete();
            $this->currentBatchId = null;
            session()->flash('message', 'Data staging berhasil dibersihkan.');
        }
    }

    public function render()
    {
        $stagingItems = $this->currentBatchId
            ? ImportStaging::where('batch_id', $this->currentBatchId)->latest()->paginate(15)
            : collect();

        $totalStaging = $this->currentBatchId
            ? ImportStaging::where('batch_id', $this->currentBatchId)->count()
            : 0;

        $unprocessedCount = $this->currentBatchId
            ? ImportStaging::where('batch_id', $this->currentBatchId)->where('is_processed', false)->count()
            : 0;

        $branches = Branch::where('active', true)->get();

        return view('livewire.imports.data-import', [
            'stagingItems' => $stagingItems,
            'totalStaging' => $totalStaging,
            'unprocessedCount' => $unprocessedCount,
            'branches' => $branches,
        ])->layout('layouts.app');
    }
}
