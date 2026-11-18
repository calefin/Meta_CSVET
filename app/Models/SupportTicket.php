<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'student_id',
        'document_submission_id',
        'subject',
        'status',
        'closed_at',
        'last_message_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function documentSubmission()
    {
        return $this->belongsTo(DocumentSubmission::class);
    }

    public function messages()
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return !$this->isOpen();
    }
}
