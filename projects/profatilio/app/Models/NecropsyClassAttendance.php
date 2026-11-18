<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NecropsyClassAttendance extends Model
{
    protected $fillable = [
        'necropsy_class_session_id',
        'necropsy_class_participant_id',
        'checked_in_at',
        'ip',
        'user_agent',
        'added_manually',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'added_manually' => 'boolean',
    ];

    public function session()
    {
        return $this->belongsTo(NecropsyClassSession::class, 'necropsy_class_session_id');
    }

    public function participant()
    {
        return $this->belongsTo(NecropsyClassParticipant::class, 'necropsy_class_participant_id');
    }
}
