<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuickPayment extends Model
{
    protected $fillable = [
        'uuid',
        'type',
        'notes',
        'user_id',
        'recommended_work_id',
        'items',
        'options',
        'selected_option_index',
        'markup_percent',
        'fee_percent',
        'subtotal',
        'total',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (QuickPayment $quickPayment) {
            if (empty($quickPayment->uuid)) {
                $quickPayment->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'options' => 'array',
            'markup_percent' => 'decimal:2',
            'fee_percent' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recommendedWork(): BelongsTo
    {
        return $this->belongsTo(RecommendedWork::class);
    }

    public function selectedOption(): ?array
    {
        return $this->selected_option_index === null
            ? null
            : ($this->options[$this->selected_option_index] ?? null);
    }

    public function filledOptions(): array
    {
        return array_filter($this->options ?? [], fn ($option) => ! empty($option));
    }

    public function hasMultipleOptions(): bool
    {
        return count($this->filledOptions()) > 1;
    }
}
