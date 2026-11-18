<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseModuleCompletion extends Model
{
    protected $fillable = [
        'course_enrollment_id',
        'course_module_id',
        'completed_at',
        'quiz_answers',
        'quiz_score',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'quiz_answers' => 'array',
        'quiz_score' => 'integer',
    ];

    public function enrollment()
    {
        return $this->belongsTo(CourseEnrollment::class, 'course_enrollment_id');
    }

    public function module()
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }
}
