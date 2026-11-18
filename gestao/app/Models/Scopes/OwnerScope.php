<?php

namespace App\Models\Scopes;

use App\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OwnerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($ownerUserId = OwnerContext::get()) {
            $builder->where($model->getTable() . '.owner_user_id', $ownerUserId);
        }
    }
}
