<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramCourseLesson extends Model
{
    protected $fillable = [
        'title',
        'description',
        'content',
        'type',
        'audio_path',
        'document_path',
        'checklist_materials',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }
}
