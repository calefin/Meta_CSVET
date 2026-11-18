<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalClassParticipant extends Model
{
    protected $fillable = [
        'external_class_group_id',
        'name',
        'rgm',
        'email',
    ];

    public function group()
    {
        return $this->belongsTo(ExternalClassGroup::class, 'external_class_group_id');
    }

    public function submissions()
    {
        return $this->hasMany(ExternalDocumentSubmission::class);
    }

    /**
     * Confere RGM + e-mail contra a lista importada da turma, escopado a ela.
     * Nunca cria/retorna nada em caso de divergência parcial — quem chama
     * deve sempre exibir uma mensagem genérica, sem indicar qual campo
     * não bateu, para não permitir que alguém confirme dados de terceiros.
     */
    public static function validateCredentials(ExternalClassGroup $group, string $rgm, string $email): ?self
    {
        $rgm = trim($rgm);
        $email = trim($email);

        if ($rgm === '' || $email === '') {
            return null;
        }

        return self::where('external_class_group_id', $group->id)
            ->whereRaw('LOWER(rgm) = ?', [mb_strtolower($rgm)])
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->first();
    }
}
