<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RitualWork extends Model
{
    protected $fillable = [
        'name',
        'description',
        'estimated_days',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function supplies(): BelongsToMany
    {
        return $this->belongsToMany(Supply::class, 'ritual_work_supplies')
            ->withPivot('default_quantity');
    }

    public function animals(): BelongsToMany
    {
        return $this->belongsToMany(Animal::class, 'ritual_work_animals')
            ->withPivot('default_quantity');
    }

    public function recommendedWorks(): HasMany
    {
        return $this->hasMany(RecommendedWork::class);
    }
}
