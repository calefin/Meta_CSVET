<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NecropsyClassParticipant extends Model
{
    protected $fillable = [
        'name',
        'rgm',
        'institution',
        'phone',
        'email',
        'student_id',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function attendances()
    {
        return $this->hasMany(NecropsyClassAttendance::class);
    }

    public function sessions()
    {
        return $this->belongsToMany(NecropsyClassSession::class, 'necropsy_class_attendances', 'necropsy_class_participant_id', 'necropsy_class_session_id')
            ->withPivot(['checked_in_at', 'added_manually'])
            ->withTimestamps();
    }

    /**
     * Tenta localizar (ou criar) um participante pelo RGM informado, vinculando
     * automaticamente a um Student existente com a mesma matrícula quando houver.
     */
    public static function findOrCreateByRgm(string $rgm, ?string $name = null, ?string $institution = null): self
    {
        $rgm = trim($rgm);

        $participant = self::whereRaw('LOWER(rgm) = ?', [mb_strtolower($rgm)])->first();

        if ($participant) {
            return $participant;
        }

        $student = Student::whereRaw('LOWER(registration) = ?', [mb_strtolower($rgm)])->first();

        return self::create([
            'name' => $name ?: ($student?->user?->name ?? 'Aluno'),
            'rgm' => $rgm,
            'institution' => $institution ?: $student?->institution,
            'student_id' => $student?->id,
        ]);
    }
}
