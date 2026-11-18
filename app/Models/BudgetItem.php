<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetItem extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'budget_id',
        'item_type',
        'item_id',
        'description',
        'quantity',
        'unit_price',
        'subtotal',
        'by_client',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'by_client' => 'boolean',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function item(): ?BelongsTo
    {
        return match ($this->item_type) {
            'animal' => $this->belongsTo(Animal::class, 'item_id'),
            'supply' => $this->belongsTo(Supply::class, 'item_id'),
            default => null,
        };
    }
}
