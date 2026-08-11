<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferNumberCounter extends Model
{
    protected $fillable = [
        'scope_key',
        'sequence_year',
        'last_sequence',
    ];

    protected function casts(): array
    {
        return [
            'sequence_year' => 'integer',
            'last_sequence' => 'integer',
        ];
    }
}
