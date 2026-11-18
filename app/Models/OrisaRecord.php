<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrisaRecord extends Model
{
    protected $fillable = [
        'user_id',
        'orisa_name',
        'spiritual_name',
        'odu_id',
        'performed_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function odu(): BelongsTo
    {
        return $this->belongsTo(Odu::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
