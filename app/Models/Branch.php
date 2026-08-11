<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'number_code',
        'name',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function offerNumberAllocations(): HasMany
    {
        return $this->hasMany(OfferNumberAllocation::class);
    }

    public function issuerProfileVersions(): HasMany
    {
        return $this->hasMany(IssuerProfileVersion::class);
    }

    public function documentSignerVersions(): HasMany
    {
        return $this->hasMany(DocumentSignerVersion::class);
    }
}
