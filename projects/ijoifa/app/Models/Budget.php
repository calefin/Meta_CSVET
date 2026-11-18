<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    protected $fillable = [
        'recommended_work_id',
        'user_id',
        'admin_id',
        'markup_percent',
        'fee_percent',
        'subtotal',
        'total',
        'status',
        'pdf_path',
        'notes',
        'options',
        'selected_option_index',
    ];

    protected function casts(): array
    {
        return [
            'markup_percent' => 'decimal:2',
            'fee_percent' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'options' => 'array',
        ];
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

    public function recommendedWork(): BelongsTo
    {
        return $this->belongsTo(RecommendedWork::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }
}
