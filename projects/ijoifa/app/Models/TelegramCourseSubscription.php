<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramCourseSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'telegram_username',
        'telegram_user_id',
        'monthly_price',
        'status',
        'terms_accepted',
        'terms_accepted_at',
        'terms_ip_address',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'terms_accepted' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payable_id')
            ->where('payable_type', self::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(TelegramCourseAttendance::class, 'telegram_course_subscription_id');
    }

    /**
     * Fixed monthly billing day (permanent rule, not per-student): every
     * renewal lands on the 15th of a month, never a rolling N-days-later
     * date. A payment always covers a full upcoming cycle, so the next due
     * date is always at least one month out from the anchor - the 15th of
     * the *following* month, even if $from is already the 15th.
     */
    public static function nextDueDate(?Carbon $currentExpiry): Carbon
    {
        $anchor = ($currentExpiry && $currentExpiry->isFuture()) ? $currentExpiry : now();

        return $anchor->copy()->addMonthNoOverflow()->day(15)->endOfDay();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at && $this->expires_at->isFuture();
    }

    public function isOverdue(): bool
    {
        if ($this->status === 'cancelled') {
            return false;
        }

        return $this->status === 'overdue' || ($this->expires_at && $this->expires_at->isPast());
    }

    public function statusLabel(): string
    {
        if ($this->status === 'cancelled') {
            return 'Cancelado';
        }

        if ($this->isActive()) {
            return 'Ativo / Adimplente';
        }

        if ($this->isOverdue()) {
            return 'Atrasado / Inadimplente';
        }

        return 'Aguardando 1º Pagamento';
    }

    public function statusBadgeClass(): string
    {
        if ($this->isActive()) {
            return 'bg-emerald-100 text-emerald-800 border-emerald-300';
        }

        if ($this->isOverdue()) {
            return 'bg-rose-100 text-rose-800 border-rose-300';
        }

        if ($this->status === 'cancelled') {
            return 'bg-slate-100 text-slate-700 border-slate-300';
        }

        return 'bg-amber-100 text-amber-800 border-amber-300';
    }
}
