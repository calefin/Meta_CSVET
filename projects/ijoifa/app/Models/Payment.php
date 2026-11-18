<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'payable_type',
        'payable_id',
        'amount',
        'status',
        'mp_payment_id',
        'mp_preference_id',
        'mp_fee_amount',
        'mp_net_amount',
        'mp_payment_type',
        'mp_payment_method',
        'checkout_url',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'mp_fee_amount' => 'decimal:2',
            'mp_net_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Human label for payment_type_id/payment_method_id as MercadoPago
     * reports them (e.g. "pix", "visa", "bolbradesco") — shown as-is since
     * there's no fixed enum on their side to map cleanly to Portuguese.
     */
    public function paymentMethodLabel(): ?string
    {
        if (! $this->mp_payment_type) {
            return null;
        }

        $type = match ($this->mp_payment_type) {
            'credit_card' => 'Cartão de crédito',
            'debit_card' => 'Cartão de débito',
            'ticket' => 'Boleto',
            'bank_transfer' => 'Transferência/Pix',
            'account_money' => 'Saldo Mercado Pago',
            default => $this->mp_payment_type,
        };

        return $this->mp_payment_method && $this->mp_payment_method !== $this->mp_payment_type
            ? $type.' ('.$this->mp_payment_method.')'
            : $type;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Reuses an existing pending payment instead of creating a duplicate
     * row, so a double click / two open tabs / an F5 on the checkout page
     * doesn't spawn a second Mercado Pago preference (and a second real
     * charge if both happen to get paid).
     *
     * $scopeToUser controls whether the lookup is also scoped to $userId:
     * - true (default) for payables that belong to one specific, already
     *   known account (a course purchase, a consulente's own budget) -
     *   here two different users legitimately have independent pending
     *   payments for the same payable (e.g. two students buying the same
     *   course), so the user must stay part of the match.
     * - false for a payable that IS the unique anchor on its own (a
     *   QuickPayment link) - whoever completes checkout might register or
     *   log in with a different email than whoever the link was sent to,
     *   so the payment must stay tied to the link regardless of identity.
     *   If an existing pending payment belonged to a different user, it's
     *   reassigned to the current one rather than duplicated.
     */
    public static function findOrCreatePending(string $payableType, int $payableId, int $userId, float $amount, bool $scopeToUser = true): self
    {
        $query = static::where('payable_type', $payableType)
            ->where('payable_id', $payableId)
            ->where('status', 'pending');

        if ($scopeToUser) {
            $query->where('user_id', $userId);
        }

        $existing = $query->latest()->first();

        if ($existing) {
            if ($existing->user_id !== $userId) {
                $existing->update(['user_id' => $userId]);
            }

            return $existing;
        }

        return static::create([
            'user_id' => $userId,
            'payable_type' => $payableType,
            'payable_id' => $payableId,
            'amount' => $amount,
            'status' => 'pending',
        ]);
    }

    public function payable(): BelongsTo
    {
        return match ($this->payable_type) {
            ConsultationRequest::class => $this->belongsTo(ConsultationRequest::class, 'payable_id'),
            Course::class => $this->belongsTo(Course::class, 'payable_id'),
            TelegramCourseSubscription::class => $this->belongsTo(TelegramCourseSubscription::class, 'payable_id'),
            AdHocCharge::class => $this->belongsTo(AdHocCharge::class, 'payable_id'),
            QuickPayment::class => $this->belongsTo(QuickPayment::class, 'payable_id'),
            default => $this->belongsTo(RecommendedWork::class, 'payable_id'),
        };
    }

    /**
     * Short, admin-facing label for whatever this payment is for,
     * regardless of which payable_type it points to.
     */
    public function description(): string
    {
        return match ($this->payable_type) {
            ConsultationRequest::class => 'Consulta oracular',
            Course::class => 'Curso — '.($this->payable?->title ?? '—'),
            TelegramCourseSubscription::class => 'Mensalidade Curso Telegram — '.($this->payable?->full_name ?? '—'),
            AdHocCharge::class => $this->payable?->description ?? 'Cobrança',
            QuickPayment::class => 'Link Rápido — ' . match($this->payable?->type ?? '') {
                'consulta' => 'Consulta',
                'trabalhos_gerais' => 'Trabalhos gerais',
                'iniciacao' => 'Iniciação',
                'aborisa' => 'Aborisa',
                default => 'Orçamento'
            } . (
                $this->payable && $this->payable->hasMultipleOptions() && $this->payable->selected_option_index !== null
                    ? ' — Opção ' . ($this->payable->selected_option_index + 1)
                    : ''
            ),
            RecommendedWork::class => 'Trabalho Recomendado (' . ($this->payable?->ritualWork?->name ?? 'Trabalho') . ')',
            default => 'Cobrança',
        };
    }
}
