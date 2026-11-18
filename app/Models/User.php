<?php

namespace App\Models;

use App\Mail\PasswordResetMail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'mother_maiden_name',
        'birthdate',
        'country',
        'cpf',
        'email',
        'phone',
        'role',
        'status',
        'locale',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public static function activeAdmins(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('role', 'admin')->where('status', 'active')->get();
    }

    public function isIniciado(): bool
    {
        return $this->role === 'iniciado';
    }

    public function isConsulente(): bool
    {
        return $this->role === 'consulente';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Branded HTML email instead of Laravel's plain default notification.
     */
    public function sendPasswordResetNotification($token)
    {
        Mail::to($this->email)->send(new PasswordResetMail($this, $token));
    }

    public function consultationRequests(): HasMany
    {
        return $this->hasMany(ConsultationRequest::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'recipient_id');
    }

    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function consentRecords(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }

    public function adminNotes(): HasMany
    {
        return $this->hasMany(AdminNote::class, 'target_user_id');
    }

    public function deletionRequests(): HasMany
    {
        return $this->hasMany(DeletionRequest::class);
    }

    public function initiation(): HasOne
    {
        return $this->hasOne(Initiation::class);
    }

    public function orisaRecords(): HasMany
    {
        return $this->hasMany(OrisaRecord::class);
    }

    public function libraryCategories(): BelongsToMany
    {
        return $this->belongsToMany(LibraryCategory::class, 'library_category_access', 'user_id', 'category_id')
            ->withPivot('granted_by', 'granted_at');
    }

    /**
     * Internal messaging permission matrix (spec sec. 10.1):
     * admin <-> anyone, iniciado <-> iniciado, but consulente only <-> admin.
     */
    public function canMessage(User $other): bool
    {
        if ($this->is($other)) {
            return false;
        }

        if ($this->role === 'admin' || $other->role === 'admin') {
            return true;
        }

        return $this->role === 'iniciado' && $other->role === 'iniciado';
    }
}
