<?php

namespace App\Livewire\WorkOrders;

use App\Models\Delivery;
use App\Models\Document;
use App\Models\Report;
use App\Models\StatusHistory;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderAsset;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    public WorkOrder $workOrder;
    public string $activeTab = 'info'; // info, assets, reports, deliveries, documents

    // Status transition state
    public string $next_status = '';
    public string $status_note = '';
    public bool $showStatusModal = false;

    // Assignment state
    public ?int $selected_surveyor_id = null;
    public ?int $selected_reviewer_id = null;
    public bool $showAssignModal = false;

    // SLA Edit state
    public string $edit_sla_date = '';
    public bool $edit_survey_required = true;
    public bool $showSlaModal = false;

    // --- FASE 3: ASSET STATE ---
    public bool $showAssetModal = false;
    public ?int $editingAssetId = null;
    public string $asset_type = 'tanah_bangunan';
    public string $asset_address = '';
    public string $asset_city = '';
    public string $asset_province = '';
    public string $asset_description = '';

    // --- FASE 3: REPORT STATE ---
    public bool $showReportModal = false;
    public ?int $editingReportId = null;
    public string $report_no = '';
    public string $report_date = '';
    public string $report_purpose = 'Penjaminan Utang';
    public float $resume_value = 0;
    public float $report_value = 0;
    public string $print_date = '';
    public array $selected_asset_ids = [];

    // --- FASE 3: DELIVERY STATE ---
    public bool $showDeliveryModal = false;
    public ?int $delivery_report_id = null;
    public string $sent_date = '';
    public string $courier = 'JNE';
    public string $tracking_no = '';
    public string $received_date = '';
    public string $recipient_name = '';
    public string $delivery_note = '';

    // --- FASE 3: DOCUMENT UPLOAD STATE ---
    public bool $showDocumentModal = false;
    public string $doc_title = '';
    public string $doc_type = 'scan_final';
    public $upload_file = null;

    public function mount(int $id): void
    {
        $this->workOrder = WorkOrder::with([
            'offer.branch',
            'offer.debtor',
            'offer.client',
            'offer.reportUser',
            'assignments.user',
            'statusHistories.user',
            'assets',
            'reports.assets',
            'reports.delivery',
            'documents.uploader',
        ])->findOrFail($id);

        $this->next_status = $this->workOrder->current_status;
        $this->edit_sla_date = $this->workOrder->sla_date ? $this->workOrder->sla_date->format('Y-m-d') : '';
        $this->edit_survey_required = $this->workOrder->survey_required;
    }

    // --- STATUS & SLA & ASSIGNMENT METHODS ---
    public function openStatusModal(string $status): void
    {
        $this->next_status = $status;
        $this->status_note = '';
        $this->showStatusModal = true;
    }

    public function updateStatus(): void
    {
        $this->authorize('work-orders.change-status');
        $this->validate([
            'next_status' => 'required|in:PERSIAPAN,SURVEY,PENGERJAAN,REVIEW,CETAK,SELESAI,BATAL',
            'status_note' => 'nullable|string',
        ]);

        $oldStatus = $this->workOrder->current_status;

        StatusHistory::create([
            'work_order_id' => $this->workOrder->id,
            'from_status' => $oldStatus,
            'to_status' => $this->next_status,
            'changed_by' => Auth::id(),
            'note' => $this->status_note,
        ]);

        $updateData = ['current_status' => $this->next_status];

        if ($this->next_status === 'SELESAI' && !$this->workOrder->completed_at) {
            $updateData['completed_at'] = Carbon::now();
        }

        $this->workOrder->update($updateData);

        session()->flash('message', 'Status pekerjaan berhasil diperbarui menjadi ' . $this->next_status . '.');
        $this->showStatusModal = false;
        $this->workOrder->refresh();
    }

    public function saveSlaConfig(): void
    {
        $this->authorize('work-orders.edit-sla');
        $this->validate([
            'edit_sla_date' => 'required|date',
            'edit_survey_required' => 'boolean',
        ]);

        $this->workOrder->update([
            'sla_date' => $this->edit_sla_date,
            'survey_required' => $this->edit_survey_required,
        ]);

        session()->flash('message', 'Konfigurasi SLA & Flag Survey berhasil diperbarui.');
        $this->showSlaModal = false;
        $this->workOrder->refresh();
    }

    public function saveAssignments(): void
    {
        $this->authorize('work-orders.assign-pic');
        if ($this->selected_surveyor_id) {
            WorkOrderAssignment::updateOrCreate(
                ['work_order_id' => $this->workOrder->id, 'role_type' => 'surveyor'],
                ['user_id' => $this->selected_surveyor_id, 'assigned_at' => Carbon::now()]
            );
        }

        if ($this->selected_reviewer_id) {
            WorkOrderAssignment::updateOrCreate(
                ['work_order_id' => $this->workOrder->id, 'role_type' => 'reviewer'],
                ['user_id' => $this->selected_reviewer_id, 'assigned_at' => Carbon::now()]
            );
        }

        session()->flash('message', 'Penugasan PIC berhasil diperbarui.');
        $this->showAssignModal = false;
        $this->workOrder->refresh();
    }

    // --- FASE 3: ASSET METHODS ---
    public function createAsset(): void
    {
        $this->reset(['editingAssetId', 'asset_type', 'asset_address', 'asset_city', 'asset_province', 'asset_description']);
        $this->asset_type = 'tanah_bangunan';
        $this->showAssetModal = true;
    }

    public function editAsset(int $id): void
    {
        $asset = WorkOrderAsset::findOrFail($id);
        $this->editingAssetId = $asset->id;
        $this->asset_type = $asset->asset_type;
        $this->asset_address = $asset->address ?? '';
        $this->asset_city = $asset->city ?? '';
        $this->asset_province = $asset->province ?? '';
        $this->asset_description = $asset->description ?? '';
        $this->showAssetModal = true;
    }

    public function saveAsset(): void
    {
        $this->authorize('work-orders.survey');
        $validated = $this->validate([
            'asset_type' => 'required|string',
            'asset_address' => 'nullable|string',
            'asset_city' => 'nullable|string',
            'asset_province' => 'nullable|string',
            'asset_description' => 'nullable|string',
        ]);

        if ($this->editingAssetId) {
            WorkOrderAsset::findOrFail($this->editingAssetId)->update([
                'asset_type' => $validated['asset_type'],
                'address' => $validated['asset_address'],
                'city' => $validated['asset_city'],
                'province' => $validated['asset_province'],
                'description' => $validated['asset_description'],
            ]);
            session()->flash('message', 'Data aset berhasil diperbarui.');
        } else {
            $this->workOrder->assets()->create([
                'asset_type' => $validated['asset_type'],
                'address' => $validated['asset_address'],
                'city' => $validated['asset_city'],
                'province' => $validated['asset_province'],
                'description' => $validated['asset_description'],
            ]);
            session()->flash('message', 'Aset baru berhasil ditambahkan.');
        }

        $this->showAssetModal = false;
        $this->workOrder->refresh();
    }

    public function deleteAsset(int $id): void
    {
        $this->authorize('work-orders.survey');
        WorkOrderAsset::findOrFail($id)->delete();
        session()->flash('message', 'Data aset berhasil dihapus.');
        $this->workOrder->refresh();
    }

    // --- FASE 3: REPORT METHODS ---
    public function createReport(): void
    {
        $this->reset(['editingReportId', 'report_no', 'report_purpose', 'resume_value', 'report_value', 'print_date', 'selected_asset_ids']);
        $this->report_date = Carbon::today()->format('Y-m-d');
        $this->report_purpose = 'Penjaminan Utang';

        // Nomor Laporan mengikuti Nomor Kontrak pekerjaan ini (tidak dibuat terpisah)
        $this->report_no = $this->workOrder->contract_no;
        $this->selected_asset_ids = $this->workOrder->assets->pluck('id')->toArray();

        $this->showReportModal = true;
    }

    public function editReport(int $id): void
    {
        $report = Report::with('assets')->findOrFail($id);
        $this->editingReportId = $report->id;
        $this->report_no = $report->report_no;
        $this->report_date = $report->report_date->format('Y-m-d');
        $this->report_purpose = $report->purpose;
        $this->resume_value = (float) $report->resume_value;
        $this->report_value = (float) $report->report_value;
        $this->print_date = $report->print_date ? $report->print_date->format('Y-m-d') : '';
        $this->selected_asset_ids = $report->assets->pluck('id')->toArray();
        $this->showReportModal = true;
    }

    public function saveReport(): void
    {
        $this->authorize('work-orders.review');
        $validated = $this->validate([
            'report_date' => 'required|date',
            'report_purpose' => 'required|string|max:255',
            'resume_value' => 'nullable|numeric|min:0',
            'report_value' => 'nullable|numeric|min:0',
            'print_date' => 'nullable|date',
        ]);

        // Nomor Laporan selalu mengikuti Nomor Kontrak pekerjaan ini
        $reportNo = $this->workOrder->contract_no;

        $duplicate = Report::where('report_no', $reportNo)
            ->when($this->editingReportId, fn ($q) => $q->where('id', '!=', $this->editingReportId))
            ->exists();

        if ($duplicate) {
            session()->flash('error', "Pekerjaan ini sudah memiliki Laporan dengan nomor {$reportNo} (mengikuti Nomor Kontrak). Edit laporan yang sudah ada, jangan buat baru.");
            return;
        }

        $data = [
            'work_order_id' => $this->workOrder->id,
            'report_no' => $reportNo,
            'report_date' => $validated['report_date'],
            'purpose' => $validated['report_purpose'],
            'resume_value' => $validated['resume_value'] ?? 0,
            'report_value' => $validated['report_value'] ?? 0,
            'print_date' => $validated['print_date'] ?: null,
            'created_by' => Auth::id(),
        ];

        if ($this->editingReportId) {
            $report = Report::findOrFail($this->editingReportId);
            $report->update($data);
            $report->assets()->sync($this->selected_asset_ids);
            session()->flash('message', 'Laporan resmi berhasil diperbarui.');
        } else {
            $report = Report::create($data);
            $report->assets()->sync($this->selected_asset_ids);
            session()->flash('message', 'Laporan resmi baru berhasil diterbitkan.');
        }

        $this->showReportModal = false;
        $this->workOrder->refresh();
    }

    public function deleteReport(int $id): void
    {
        $this->authorize('work-orders.review');
        Report::findOrFail($id)->delete();
        session()->flash('message', 'Laporan berhasil dihapus.');
        $this->workOrder->refresh();
    }

    // --- FASE 3: DELIVERY METHODS ---
    public function openDeliveryModal(int $reportId): void
    {
        $this->delivery_report_id = $reportId;
        $delivery = Delivery::where('report_id', $reportId)->first();

        if ($delivery) {
            $this->sent_date = $delivery->sent_date->format('Y-m-d');
            $this->courier = $delivery->courier;
            $this->tracking_no = $delivery->tracking_no ?? '';
            $this->received_date = $delivery->received_date ? $delivery->received_date->format('Y-m-d') : '';
            $this->recipient_name = $delivery->recipient_name ?? '';
            $this->delivery_note = $delivery->note ?? '';
        } else {
            $this->reset(['courier', 'tracking_no', 'received_date', 'recipient_name', 'delivery_note']);
            $this->sent_date = Carbon::today()->format('Y-m-d');
            $this->courier = 'JNE';
        }

        $this->showDeliveryModal = true;
    }

    public function saveDelivery(): void
    {
        $this->authorize('work-orders.review');
        $validated = $this->validate([
            'sent_date' => 'required|date',
            'courier' => 'required|string|max:100',
            'tracking_no' => 'nullable|string|max:100',
            'received_date' => 'nullable|date',
            'recipient_name' => 'nullable|string|max:255',
            'delivery_note' => 'nullable|string',
        ]);

        Delivery::updateOrCreate(
            ['report_id' => $this->delivery_report_id],
            [
                'sent_date' => $validated['sent_date'],
                'courier' => $validated['courier'],
                'tracking_no' => $validated['tracking_no'],
                'received_date' => $validated['received_date'] ?: null,
                'recipient_name' => $validated['recipient_name'],
                'note' => $validated['delivery_note'],
            ]
        );

        session()->flash('message', 'Data bukti pengiriman laporan berhasil disimpan.');
        $this->showDeliveryModal = false;
        $this->workOrder->refresh();
    }

    // --- FASE 3: DOCUMENT STORAGE METHODS ---
    public function openDocumentModal(): void
    {
        $this->reset(['doc_title', 'doc_type', 'upload_file']);
        $this->doc_type = 'scan_final';
        $this->showDocumentModal = true;
    }

    public function uploadDocument(): void
    {
        $this->authorize('work-orders.review');
        $this->validate([
            'doc_title' => 'required|string|max:255',
            'doc_type' => 'required|string',
            'upload_file' => 'required|file|max:10240', // max 10MB
        ]);

        $path = $this->upload_file->store('documents/work_orders', 'public');

        Document::create([
            'work_order_id' => $this->workOrder->id,
            'title' => $this->doc_title,
            'type' => $this->doc_type,
            'file_path' => $path,
            'file_size' => $this->upload_file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        session()->flash('message', 'Dokumen berhasil diupload dan disimpan ke arsip online.');
        $this->showDocumentModal = false;
        $this->workOrder->refresh();
    }

    public function deleteDocument(int $id): void
    {
        $this->authorize('work-orders.review');
        $doc = Document::findOrFail($id);
        if (Storage::disk('public')->exists($doc->file_path)) {
            Storage::disk('public')->delete($doc->file_path);
        }
        $doc->delete();

        session()->flash('message', 'Dokumen arsip berhasil dihapus.');
        $this->workOrder->refresh();
    }

    public function render()
    {
        $surveyors = User::whereIn('role', ['surveyor', 'admin', 'sysadmin'])->where('active', true)->get();
        $reviewers = User::whereIn('role', ['reviewer', 'admin', 'sysadmin'])->where('active', true)->get();

        return view('livewire.work-orders.show', [
            'surveyors' => $surveyors,
            'reviewers' => $reviewers,
        ])->layout('layouts.app');
    }
}
