<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Curso extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'hours',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function matriculas()
    {
        return $this->hasMany(Matricula::class, 'curso_id');
    }
}
