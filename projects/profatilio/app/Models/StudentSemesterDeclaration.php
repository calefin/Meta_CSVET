<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSemesterDeclaration extends Model
{
    protected $fillable = [
        'student_id',
        'semester',
        'situations',
        'concluded',
        'declared_at',
    ];

    protected $casts = [
        'situations' => 'array',
        'concluded' => 'boolean',
        'declared_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public static function situationLabels(): array
    {
        return [
            'tcc' => 'Cursando TCC',
            'estagio' => 'Em Estágio',
            'ic' => 'Iniciação Científica',
        ];
    }
}
