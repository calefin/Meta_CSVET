<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Odu extends Model
{
    protected $fillable = [
        'name',
        'number',
        'category',
        'description',
        'alternative_names',
        'summary',
        'ebos',
        'orisas',
        'binary_mark',
    ];

    protected $casts = [
        'alternative_names' => 'array',
        'summary' => 'array',
        'ebos' => 'array',
        'orisas' => 'array',
    ];
}
