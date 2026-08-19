<?php

namespace App\Models;

use App\Enums\OfferTemplateCategory;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferTemplate extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $template): void {
            if (! $template->isDirty(['code', 'name', 'purpose', 'category', 'active', 'is_default'])) {
                return;
            }

            $hasFrozenVersion = $template->versions()
                ->whereIn('status', ['submitted', 'approved', 'rejected', 'retired'])
                ->exists();

            if ($hasFrozenVersion) {
                throw new DomainException('Identitas dan konfigurasi template dengan riwayat review bersifat immutable; gunakan workflow versi master.');
            }
        });

        static::deleting(function (self $template): void {
            if ($template->versions()->exists()) {
                throw new DomainException('Template yang sudah memiliki versi tidak dapat dihapus.');
            }
        });
    }

    protected $fillable = [
        'code',
        'name',
        'purpose',
        'category',
        'active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'category' => OfferTemplateCategory::class,
            'active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(OfferTemplateVersion::class)->orderBy('version_no');
    }
}
