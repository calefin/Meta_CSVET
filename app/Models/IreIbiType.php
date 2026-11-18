<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IreIbiType extends Model
{
    protected $fillable = [
        'classification',
        'name',
        'description',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
