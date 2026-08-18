<?php

namespace App\Models\Concerns;

use App\Services\Offers\OfferDocumentMasterIntegrityService;
use DomainException;
use Illuminate\Database\Eloquent\Model;

trait HasImmutableOfferDocumentApproval
{
    public static function bootHasImmutableOfferDocumentApproval(): void
    {
        static::saving(function (Model $model): void {
            if ($model->exists && in_array($model->getOriginal('status'), ['approved', 'retired'], true)) {
                throw new DomainException('Master yang sudah approved atau retired bersifat immutable; buat versi baru untuk perubahan.');
            }

            if ($model->getAttribute('status') === null) {
                $model->setAttribute('status', 'draft');
            }

            if (! in_array($model->getAttribute('status'), ['draft', 'approved', 'retired'], true)) {
                throw new DomainException('Status master dokumen tidak valid.');
            }

            if ($model->getAttribute('status') !== 'draft') {
                throw new DomainException('Status approved atau retired hanya dapat diberikan melalui layanan approval resmi.');
            }

            $integrity = app(OfferDocumentMasterIntegrityService::class);
            $model->setAttribute('checksum', $integrity->checksum($model));

            $model->setAttribute('approved_by', null);
            $model->setAttribute('approved_at', null);
        });

        static::deleting(function (Model $model): void {
            if (in_array($model->getOriginal('status'), ['approved', 'retired'], true)) {
                throw new DomainException('Master yang sudah approved atau retired tidak dapat dihapus.');
            }
        });
    }
}
