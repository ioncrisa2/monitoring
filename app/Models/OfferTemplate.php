<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'purpose',
        'active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(OfferTemplateVersion::class)->orderBy('version_no');
    }
}
