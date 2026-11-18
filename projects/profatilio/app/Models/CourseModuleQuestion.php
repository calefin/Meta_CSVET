<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseModuleQuestion extends Model
{
    protected $fillable = [
        'course_module_id',
        'student_id',
        'question',
        'answer',
        'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    public function module()
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function isAnswered(): bool
    {
        return filled($this->answer);
    }
}
