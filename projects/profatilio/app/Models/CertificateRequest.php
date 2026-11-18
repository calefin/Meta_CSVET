<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateRequest extends Model
{
    protected $fillable = [
        'student_id',
        'course_id',
        'status',
        'cert_hash',
        'signed_pdf_path',
        'admin_notes',
        'requested_at',
        'approved_at',
        'available_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at'  => 'datetime',
        'available_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'  => 'Aguardando Aprovação',
            'approved' => 'Aprovado — Aguardando Assinatura',
            'available' => 'Certificado Disponível',
            default    => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending'   => 'warning',
            'approved'  => 'info',
            'available' => 'success',
            default     => 'gray',
        };
    }
}
