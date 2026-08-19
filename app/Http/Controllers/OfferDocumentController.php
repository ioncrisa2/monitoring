<?php

namespace App\Http\Controllers;

use App\Enums\OfferDocumentArtifactType;
use App\Enums\OfferDocumentStorageStatus;
use App\Models\Offer;
use App\Models\OfferDocumentArtifact;
use App\Models\OfferDocumentVersion;
use App\Services\AuditLogService;
use App\Services\Offers\OfferDocumentRenderer;
use App\Services\Offers\OfferDocumentWorkflowService;
use App\Services\Offers\OfferPreflightValidator;
use App\Services\Offers\OfferSnapshotBuilder;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class OfferDocumentController extends Controller
{
    public function __construct(
        private readonly OfferSnapshotBuilder $snapshotBuilder,
        private readonly OfferPreflightValidator $preflightValidator,
        private readonly OfferDocumentRenderer $renderer,
        private readonly OfferDocumentWorkflowService $workflow,
    ) {}

    /**
     * Render an authorized, non-persisted PDF draft in the browser.
     */
    public function preview(Offer $offer): Response
    {
        Gate::authorize('generateDocumentDraft', $offer);

        $response = $this->draftResponse($offer, 'inline');
        AuditLogService::record(
            'PREVIEW_DRAFT',
            "Membuat pratinjau PDF draft penawaran {$offer->offer_no}",
            'Offer',
            $offer->getKey(),
        );

        return $response;
    }

    /**
     * Download an authorized, non-persisted PDF draft.
     */
    public function download(Offer $offer): Response
    {
        Gate::authorize('generateDocumentDraft', $offer);

        $response = $this->draftResponse($offer, 'attachment');
        AuditLogService::record(
            'DOWNLOAD_DRAFT',
            "Mengunduh PDF draft penawaran {$offer->offer_no}",
            'Offer',
            $offer->getKey(),
        );

        return $response;
    }

    public function submit(Offer $offer, Request $request): RedirectResponse
    {
        Gate::authorize('manageDocument', $offer);
        Gate::authorize('generateDocumentDraft', $offer);

        try {
            $version = $this->workflow->submit($offer, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['workflow' => $exception->getMessage()]);
        }

        return back()->with('message', "Dokumen versi {$version->version_no} diajukan untuk review.");
    }

    public function approve(
        Offer $offer,
        OfferDocumentVersion $version,
        Request $request,
    ): RedirectResponse {
        Gate::authorize('generateDocumentPrintReady', $offer);
        $this->assertVersionBelongsToOffer($offer, $version);

        try {
            $this->workflow->approve($version, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['workflow' => $exception->getMessage()]);
        }

        return back()->with('message', "Snapshot versi {$version->version_no} disetujui.");
    }

    public function reject(
        Offer $offer,
        OfferDocumentVersion $version,
        Request $request,
    ): RedirectResponse {
        Gate::authorize('generateDocumentPrintReady', $offer);
        $this->assertVersionBelongsToOffer($offer, $version);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->workflow->reject($version, $request->user(), $validated['reason']);
        } catch (DomainException $exception) {
            return back()->withErrors(['workflow' => $exception->getMessage()]);
        }

        return back()->with('message', "Snapshot versi {$version->version_no} ditolak dan dikembalikan untuk revisi.");
    }

    public function finalize(
        Offer $offer,
        OfferDocumentVersion $version,
        Request $request,
    ): RedirectResponse {
        Gate::authorize('generateDocumentPrintReady', $offer);
        $this->assertVersionBelongsToOffer($offer, $version);

        try {
            $artifact = $this->workflow->finalize($version, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['workflow' => $exception->getMessage()]);
        }

        return back()->with('message', "PDF final versi {$version->version_no} tersimpan sebagai artifact #{$artifact->getKey()}.");
    }

    /** Download the current immutable final artifact; never rebuild live data. */
    public function printReady(Offer $offer): Response
    {
        Gate::authorize('generateDocumentPrintReady', $offer);

        $offer->load('engagement.currentFinalVersion.artifacts');
        $version = $offer->engagement?->currentFinalVersion;
        $artifact = $version?->artifacts
            ->first(fn (OfferDocumentArtifact $candidate): bool => $candidate->artifact_type === OfferDocumentArtifactType::Final
                && $candidate->storage_status === OfferDocumentStorageStatus::Ready
                && $candidate->final_slot === 1
            );

        if (! $version instanceof OfferDocumentVersion || ! $artifact instanceof OfferDocumentArtifact) {
            abort(422, 'PDF siap cetak belum difinalkan dan belum tersedia di arsip.');
        }

        abort_unless((int) $version->offer_id === (int) $offer->getKey(), 409);

        $response = $this->artifactResponse($artifact);

        AuditLogService::record(
            'DOWNLOAD_PRINT_READY',
            "Mengunduh artifact final versi {$version->version_no} untuk penawaran {$offer->offer_no}",
            'OfferDocumentArtifact',
            $artifact->getKey(),
        );

        return $response;
    }

    /** Download one historical immutable artifact within this offer. */
    public function artifact(
        Offer $offer,
        OfferDocumentVersion $version,
        OfferDocumentArtifact $artifact,
    ): Response {
        abort_unless((int) $version->offer_id === (int) $offer->getKey(), 404);
        abort_unless((int) $artifact->offer_document_version_id === (int) $version->getKey(), 404);

        if ($artifact->artifact_type === OfferDocumentArtifactType::Final) {
            Gate::authorize('generateDocumentPrintReady', $offer);
        } else {
            Gate::authorize('generateDocumentDraft', $offer);
        }

        $response = $this->artifactResponse($artifact);
        AuditLogService::record(
            'DOWNLOAD_OFFER_DOCUMENT_ARTIFACT',
            "Mengunduh artifact {$artifact->artifact_type->value} dokumen penawaran versi {$version->version_no}",
            'OfferDocumentArtifact',
            $artifact->getKey(),
        );

        return $response;
    }

    private function draftResponse(Offer $offer, string $disposition): Response
    {
        $snapshot = $this->snapshotBuilder->build($offer);
        $preflight = $this->preflightValidator->validate($snapshot);
        $errors = $preflight['errors'] ?? [];

        if ($errors !== []) {
            abort(422, 'PDF draft belum dapat dibuat karena data penawaran belum lengkap.');
        }

        $pdf = $this->renderer->render($snapshot);

        return $this->pdfResponse($pdf, $this->offerFilename($offer), $disposition);
    }

    private function offerFilename(Offer $offer): string
    {
        $number = trim((string) $offer->offer_no);
        $number = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F\x7F]+/u', '-', $number) ?? '';
        $number = trim(Str::limit($number, 160, ''), ' .-');

        if ($number === '') {
            $number = (string) $offer->getKey();
        }

        return "Penawaran-{$number}.pdf";
    }

    private function pdfResponse(string $pdf, string $filename, string $disposition): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function artifactResponse(OfferDocumentArtifact $artifact): Response
    {
        try {
            $pdf = $this->workflow->readArtifact($artifact);
        } catch (DomainException) {
            abort(409, 'Integritas artifact dokumen tidak valid.');
        }

        $filename = trim((string) $artifact->original_filename);
        $filename = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F\x7F]+/u', '-', $filename) ?? '';
        $filename = trim(Str::limit($filename, 180, ''), ' .-');

        if ($filename === '') {
            $filename = 'Penawaran-'.$artifact->getKey().'.pdf';
        }

        return $this->pdfResponse($pdf, $filename, 'attachment');
    }

    private function assertVersionBelongsToOffer(Offer $offer, OfferDocumentVersion $version): void
    {
        abort_unless((int) $version->offer_id === (int) $offer->getKey(), 404);
    }
}
