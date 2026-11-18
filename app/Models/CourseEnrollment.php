<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseEnrollment extends Model
{
    protected $fillable = [
        'course_id',
        'student_id',
        'enrolled_by',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function enrolledBy()
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function completions()
    {
        return $this->hasMany(CourseModuleCompletion::class);
    }

    public function quizAttempts()
    {
        return $this->hasMany(CourseModuleQuizAttempt::class)->latest();
    }

    public function isConcluded(): bool
    {
        return $this->status === 'concluido';
    }

    /**
     * Solicitação de inscrição feita pelo próprio aluno, ainda sem acesso
     * liberado — aguardando o professor decidir se matricula ou não.
     */
    public function isRequested(): bool
    {
        return $this->status === 'solicitado';
    }

    /**
     * Etapa atual: a primeira etapa da trilha (em ordem) que ainda não foi
     * concluída por este aluno. Garante a progressão sequencial/bloqueada.
     */
    public function currentModule(): ?CourseModule
    {
        $completedIds = $this->completions()->pluck('course_module_id')->all();

        return $this->course->modules
            ->first(fn (CourseModule $module) => ! in_array($module->id, $completedIds, true));
    }

    public function progressPercent(): int
    {
        $total = $this->course->modules->count();
        if ($total === 0) {
            return 0;
        }

        $completed = $this->completions()->count();

        return (int) round(($completed / $total) * 100);
    }
}
