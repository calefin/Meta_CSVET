<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseModuleQuizAttempt extends Model
{
    protected $fillable = [
        'course_enrollment_id',
        'course_module_id',
        'answers',
        'score',
        'passed',
    ];

    protected $casts = [
        'answers' => 'array',
        'score' => 'integer',
        'passed' => 'boolean',
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
