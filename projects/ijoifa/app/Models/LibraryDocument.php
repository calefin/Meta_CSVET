<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryDocument extends Model
{
    protected $fillable = [
        'category_id',
        'title',
        'language',
        'content',
        'file_path',
        'audio_path',
        'description',
        'order',
        'uploaded_by',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class, 'category_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
