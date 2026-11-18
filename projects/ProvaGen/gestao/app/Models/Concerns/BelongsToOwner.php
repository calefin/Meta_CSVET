<?php

namespace App\Models\Concerns;

use App\Models\Owner;
use App\Models\Scopes\OwnerScope;
use App\Support\OwnerContext;

/**
 * Aplicada em toda model de domínio (aluno, turma de orientação, processo,
 * etc.). Filtra automaticamente toda query pelo professor ativo
 * (App\Support\OwnerContext) e preenche owner_user_id sozinho ao criar um
 * registro novo, para nenhuma tela ou job precisar lembrar de fazer isso
 * manualmente.
 */
trait BelongsToOwner
{
    protected static function bootBelongsToOwner(): void
    {
        static::addGlobalScope(new OwnerScope);

        static::creating(function ($model) {
            if (empty($model->owner_user_id) && ($ownerUserId = OwnerContext::get())) {
                $model->owner_user_id = $ownerUserId;
            }
        });
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class, 'owner_user_id');
    }
}
