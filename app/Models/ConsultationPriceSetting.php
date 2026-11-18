<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Insert-only: each row is a price change, so the table itself is the audit
 * trail (see the migration). There is deliberately no update()/delete() path.
 */
class ConsultationPriceSetting extends Model
{
    protected $fillable = [
        'base_price',
        'fee_percent',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'fee_percent' => 'decimal:2',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Falls back to a R$100/6% default (matching the value hardcoded in
     * RequestsQueue before this model existed) when no admin has saved a
     * price yet — this default is never persisted on its own.
     */
    public static function current(): self
    {
        // Ordered by id, not created_at — two changes saved within the same
        // second would otherwise tie on created_at and resolve arbitrarily.
        return static::latest('id')->first() ?? new static(['base_price' => 100, 'fee_percent' => 6]);
    }

    /**
     * What the client is actually charged: base_price grossed up so the fee
     * percent is absorbed rather than subtracted, same convention used for
     * option markup/fee elsewhere (see HasPricedOptions::applyMarkupAndFee).
     */
    public function chargeAmount(): float
    {
        $base = (float) $this->base_price;
        $fee = (float) $this->fee_percent;

        return ($fee > 0 && $fee < 100) ? round($base / (1 - $fee / 100), 2) : round($base, 2);
    }
}
