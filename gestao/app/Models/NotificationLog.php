<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use \App\Models\Concerns\BelongsToOwner;

    protected $fillable = [
        'student_id',
        'academic_title_id',
        'channel',
        'category',
        'title',
        'message',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicTitle()
    {
        return $this->belongsTo(AcademicTitle::class);
    }
}
