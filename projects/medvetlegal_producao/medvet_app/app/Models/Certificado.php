<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificado extends Model
{
    use HasFactory;

    protected $fillable = [
        'matricula_id',
        'verification_code',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function matricula()
    {
        return $this->belongsTo(Matricula::class);
    }
}
