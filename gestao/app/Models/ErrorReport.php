<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorReport extends Model
{
    use \App\Models\Concerns\BelongsToOwner;

    protected $fillable = [
        'user_id',
        'category',
        'description',
        'url',
        'screenshot_path',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Pode ser aberto pelo professor (Owner) ou pelo aluno (AdviseeAccount) —
     * ver App\Models\Concerns e a migration 2026_08_24_100002.
     */
    public function user()
    {
        return $this->morphTo();
    }
}
