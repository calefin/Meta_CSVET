<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyPrice extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'supply_id',
        'supplier_id',
        'unit_price',
        'payment_method',
        'min_quantity',
        'quantity',
        'purchase_date',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'unit_price' => 'decimal:2',
            'quantity' => 'decimal:3',
        ];
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
