<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassDiaryEntry extends Model
{
    use \App\Models\Concerns\BelongsToOwner;

    protected $fillable = [
        'class_group_id',
        'date',
        'content',
        'progress_notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function classGroup()
    {
        return $this->belongsTo(ClassGroup::class);
    }
}
