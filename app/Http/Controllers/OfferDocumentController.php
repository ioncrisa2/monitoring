<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Services\AuditLogService;
use App\Services\Offers\OfferDocumentRenderer;
use App\Services\Offers\OfferPreflightValidator;
use App\Services\Offers\OfferSnapshotBuilder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
