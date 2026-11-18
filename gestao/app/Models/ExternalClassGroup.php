<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExternalClassGroup extends Model
{
    use \App\Models\Concerns\BelongsToOwner;

    protected $fillable = [
        'token',
        'university_name',
        'group_name',
        'course',
        'semester',
        'year',
        'active',
        'created_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'year' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function (self $group) {
            if (empty($group->token)) {
                $group->token = Str::random(32);
            }
        });
    }

    public function participants()
    {
        return $this->hasMany(ExternalClassParticipant::class);
    }

    public function submissions()
    {
        return $this->hasMany(ExternalDocumentSubmission::class);
    }

    public function publicUrl(): string
    {
        return route('estagio.turma', ['token' => $this->token]);
    }
}
