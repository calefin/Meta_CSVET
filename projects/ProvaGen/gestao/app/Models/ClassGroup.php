<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassGroup extends Model
{
    use \App\Models\Concerns\BelongsToOwner;

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

    /**
     * Roster desta turma — usado pela importação de turma/aluno no ProvaGen
     * (server/_core/gestaoImport.ts), pra gerar prova a partir de uma turma
     * criada aqui.
     */
    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
