<?php

namespace App\Models\Concerns;

use App\Enums\OfferDocumentMasterReviewStatus;
use App\Services\Offers\OfferDocumentMasterIntegrityService;
use DomainException;
use Illuminate\Database\Eloquent\Model;

trait HasImmutableOfferDocumentApproval
{
    public static function bootHasImmutableOfferDocumentApproval(): void
    {
        static::saving(function (Model $model): void {
            $immutableStatuses = [
                OfferDocumentMasterReviewStatus::Submitted->value,
                OfferDocumentMasterReviewStatus::Approved->value,
                OfferDocumentMasterReviewStatus::Rejected->value,
                OfferDocumentMasterReviewStatus::Retired->value,
            ];

            if ($model->exists && in_array($model->getOriginal('status'), $immutableStatuses, true)) {
                throw new DomainException('Master yang sudah diajukan, ditinjau, atau di-retire bersifat immutable; buat versi baru untuk perubahan.');
            }

            if ($model->getAttribute('status') === null) {
                $model->setAttribute('status', OfferDocumentMasterReviewStatus::Draft->value);
            }

            $validStatuses = array_column(OfferDocumentMasterReviewStatus::cases(), 'value');

            if (! in_array($model->getAttribute('status'), $validStatuses, true)) {
                throw new DomainException('Status master dokumen tidak valid.');
            }

            if ($model->getAttribute('status') !== OfferDocumentMasterReviewStatus::Draft->value) {
                throw new DomainException('Status review master hanya dapat diberikan melalui layanan approval resmi.');
            }

            $integrity = app(OfferDocumentMasterIntegrityService::class);
            $model->setAttribute('checksum', $integrity->checksum($model));

            $model->setAttribute('approved_by', null);
            $model->setAttribute('approved_at', null);
            $model->setAttribute('submitted_by', null);
            $model->setAttribute('submitted_at', null);
            $model->setAttribute('reviewed_by', null);
            $model->setAttribute('reviewed_at', null);
            $model->setAttribute('rejection_note', null);
        });

        static::deleting(function (Model $model): void {
            if ($model->getOriginal('status') !== OfferDocumentMasterReviewStatus::Draft->value) {
                throw new DomainException('Master yang sudah masuk workflow review tidak dapat dihapus.');
            }
        });
    }
}
