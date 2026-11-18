<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkTemplate extends Model
{
    protected $fillable = [
        'name',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
