<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'title',
        'description',
        'cover_image',
        'target',
        'price',
        'required_orisa_name',
        'required_odu_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function requiredOdu(): BelongsTo
    {
        return $this->belongsTo(Odu::class, 'required_odu_id');
    }

    public function hasInitiationCriteria(): bool
    {
        return (bool) ($this->required_orisa_name || $this->required_odu_id);
    }

    /**
     * Whether the user already has the spiritual characteristic (a specific
     * Orisá received, or a specific Odù of initiation) that this course
     * requires, regardless of payment. Either criterion matching is enough.
     */
    public function userMeetsInitiationCriteria(User $user): bool
    {
        if (! $this->hasInitiationCriteria()) {
            return false;
        }

        if ($this->required_orisa_name) {
            $hasOrisa = $user->orisaRecords()
                ->where('orisa_name', $this->required_orisa_name)
                ->exists();

            if ($hasOrisa) {
                return true;
            }
        }

        if ($this->required_odu_id) {
            $hasOduViaInitiation = $user->initiation()
                ->where('odu_id', $this->required_odu_id)
                ->exists();

            $hasOduViaOrisaRecord = $user->orisaRecords()
                ->where('odu_id', $this->required_odu_id)
                ->exists();

            if ($hasOduViaInitiation || $hasOduViaOrisaRecord) {
                return true;
            }
        }

        return false;
    }

    /**
     * Central access rule: consulentes never get course content, no matter
     * the target/price/criteria. Iniciados get in via enrollment, free
     * price, or a matching initiation characteristic; otherwise they must
     * buy access.
     */
    public function userCanAccess(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isConsulente()) {
            return false;
        }

        if ($this->enrollments()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ((float) $this->price === 0.0) {
            return true;
        }

        return $this->userMeetsInitiationCriteria($user);
    }
}
