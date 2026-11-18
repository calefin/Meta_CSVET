<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseModule extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'type',
        'content',
        'video_url',
        'practice_exercises',
        'quiz_questions',
        'passing_score',
        'order',
    ];

    protected $casts = [
        'quiz_questions' => 'array',
        'practice_exercises' => 'array',
        'passing_score' => 'integer',
        'order' => 'integer',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function completions()
    {
        return $this->hasMany(CourseModuleCompletion::class);
    }

    public function questions()
    {
        return $this->hasMany(CourseModuleQuestion::class)->latest();
    }

    public function isAvaliacao(): bool
    {
        return $this->type === 'avaliacao';
    }

    /**
     * Converte um link normal do YouTube (watch?v=, youtu.be/, shorts/) para
     * a URL de embed usada no iframe da trilha.
     */
    public function getYoutubeEmbedUrlAttribute(): ?string
    {
        if (blank($this->video_url)) {
            return null;
        }

        $id = null;

        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([A-Za-z0-9_-]{6,})/', $this->video_url, $matches)) {
            $id = $matches[1];
        }

        return $id ? "https://www.youtube.com/embed/{$id}" : null;
    }
}
