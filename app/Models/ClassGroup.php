<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassGroup extends Model
{
    protected $fillable = [
        'name',
        'semester',
        'shift',
        'active',
        'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function diaryEntries()
    {
        return $this->hasMany(ClassDiaryEntry::class);
    }
}
