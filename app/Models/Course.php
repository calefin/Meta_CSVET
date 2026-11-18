<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'name',
        'workload',
        'start_date',
        'end_date',
        'syllabus',
        'active',
        'isbn',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'active'     => 'boolean',
    ];

    public function certificateRequests()
    {
        return $this->hasMany(CertificateRequest::class);
    }

    public function modules()
    {
        return $this->hasMany(CourseModule::class)->orderBy('order');
    }

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /**
     * Diferencia um curso interativo (trilha com etapas) de um curso "de
     * registro" (ex.: cursos externos da CSvet, que só existem para gerar
     * certificado sob solicitação, sem conteúdo dentro da plataforma).
     */
    public function hasModules(): bool
    {
        return $this->modules()->exists();
    }

    public function getFormattedPeriodAttribute(): string
    {
        return $this->start_date->format('d/m/Y') . ' a ' . $this->end_date->format('d/m/Y');
    }

    public function getEndDateFormattedAttribute(): string
    {
        $months = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
        return $this->end_date->format('d') . ' de ' . $months[$this->end_date->month] . ' de ' . $this->end_date->format('Y');
    }
}
