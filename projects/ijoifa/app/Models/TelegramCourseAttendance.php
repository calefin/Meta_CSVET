<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramCourseAttendance extends Model
{
    protected $fillable = [
        'telegram_course_subscription_id',
        'meeting_date',
        'attended',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'attended' => 'boolean',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TelegramCourseSubscription::class, 'telegram_course_subscription_id');
    }
}
