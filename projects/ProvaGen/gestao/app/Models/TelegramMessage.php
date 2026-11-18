<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramMessage extends Model
{
    use \App\Models\Concerns\BelongsToOwner;

    protected $fillable = [
        'student_id',
        'chat_id',
        'message_text',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    /**
     * Relationship with the Student model.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
