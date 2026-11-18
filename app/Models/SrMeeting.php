<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ata curta da reunião quinzenal do projeto. */
class SrMeeting extends Model
{
    protected $table = 'sr_meetings';

    protected $fillable = ['systematic_review_id', 'held_at', 'participants', 'decisions', 'pending', 'next_at'];

    protected $casts = ['held_at' => 'datetime', 'next_at' => 'date'];

    public function systematicReview()
    {
        return $this->belongsTo(SystematicReview::class);
    }
}
