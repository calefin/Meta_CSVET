<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PressMention extends Model
{
    protected $fillable = [
        'title',
        'url',
        'outlet',
        'section',
        'author',
        'published_at',
        'excerpt',
    ];

    protected $casts = [
        'published_at' => 'date',
    ];
}
