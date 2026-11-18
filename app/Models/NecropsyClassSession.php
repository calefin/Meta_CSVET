<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NecropsyClassSession extends Model
{
    protected $fillable = [
        'token',
        'class_date',
        'start_time',
        'end_time',
        'location',
        'animals',
        'diary',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
    ];

    protected $casts = [
        'class_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (self $session) {
            if (empty($session->token)) {
                $session->token = \Illuminate\Support\Str::random(32);
            }
        });
    }

    public function attendances()
    {
        return $this->hasMany(NecropsyClassAttendance::class);
    }

    public function participants()
    {
        return $this->belongsToMany(NecropsyClassParticipant::class, 'necropsy_class_attendances', 'necropsy_class_session_id', 'necropsy_class_participant_id')
            ->withPivot(['checked_in_at', 'added_manually'])
            ->withTimestamps();
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Duração da tarde em horas (padrão 13h-17h = 4h), usada para somar a
     * carga horária de cada participante no relatório de frequência.
     */
    public function durationHours(): float
    {
        $start = \Carbon\Carbon::parse($this->start_time ?: '13:00');
        $end = \Carbon\Carbon::parse($this->end_time ?: '17:00');

        return round(abs($end->diffInMinutes($start)) / 60, 2);
    }

    public function checkInUrl(): string
    {
        return route('necropsy.class.checkin', ['token' => $this->token]);
    }
}
