<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use \App\Models\Concerns\BelongsToOwner;

    protected $fillable = [
        'client_name',
        'client_email',
        'service_type',
        'amount',
        'status',
        'valid_until',
        'google_drive_file_id',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'amount' => 'decimal:2',
    ];
}
