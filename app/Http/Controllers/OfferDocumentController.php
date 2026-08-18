<?php

namespace App\Http\Controllers;

use App\Enums\OfferDocumentOutputMode;
use App\Models\Offer;
use App\Services\AuditLogService;
use App\Services\Offers\OfferDocumentRenderer;
use App\Services\Offers\OfferPreflightValidator;
use App\Services\Offers\OfferSnapshotBuilder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OfferDocumentController extends Controller
{
    public function __construct(
        private readonly OfferSnapshotBuilder $snapshotBuilder,
        private readonly OfferPreflightValidator $preflightValidator,
        private readonly OfferDocumentRenderer $renderer,
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

    /**
     * Generate an authorized, non-persisted PDF ready for physical printing.
     */
    public function printReady(Offer $offer): Response
    {
        Gate::authorize('generateDocumentPrintReady', $offer);

        $snapshot = $this->snapshotBuilder->build($offer);
        $preflight = $this->preflightValidator->validate(
            $snapshot,
            OfferPreflightValidator::MODE_PRINT_READY,
        );

        if (($preflight['errors'] ?? []) !== []) {
            abort(422, 'PDF siap cetak belum dapat dibuat karena data penawaran belum lengkap atau belum disetujui.');
        }

        try {
            $pdf = $this->renderer->render($snapshot, OfferDocumentOutputMode::PrintReady);
        } catch (InvalidArgumentException) {
            abort(422, 'PDF siap cetak belum dapat dibuat karena kontrak dokumen tidak valid.');
        }
        $safeNumber = Str::limit(Str::slug($offer->offer_no), 100, '') ?: (string) $offer->getKey();
        $safeClient = Str::limit(Str::slug((string) ($snapshot['recipient']['name'] ?? '')), 80, '');
        $filename = 'penawaran-'.$safeNumber.($safeClient !== '' ? '-'.$safeClient : '').'.pdf';
        $response = $this->pdfResponse($pdf, $filename, 'attachment');

        AuditLogService::record(
            'GENERATE_PRINT_READY',
            "Membuat dan mengunduh PDF siap cetak penawaran {$offer->offer_no}",
            'Offer',
            $offer->getKey(),
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
        $safeNumber = Str::slug($offer->offer_no) ?: (string) $offer->getKey();
        $filename = "penawaran-{$safeNumber}-draft.pdf";

        return $this->pdfResponse($pdf, $filename, $disposition);
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
}
