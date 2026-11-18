<?php

namespace App\Services;

use App\Models\QuickPayment;
use Illuminate\Support\Str;

class QuickPaymentService
{
    /**
     * Persists a QuickPayment. The caller is responsible for validating input
     * and building the $options array (up to 3 slots: null, or ['items',
     * 'subtotal', 'freight', 'profit', 'labor_cost', 'total', 'comment']).
     */
    public static function generate(
        string $type,
        ?string $notes,
        array $options,
        string $markupPercent,
        string $feePercent,
        ?int $userId = null,
        ?int $recommendedWorkId = null,
    ): QuickPayment {
        return QuickPayment::create([
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'notes' => $notes,
            'user_id' => $userId,
            'recommended_work_id' => $recommendedWorkId,
            'items' => $options[0]['items'] ?? [],
            'options' => $options,
            'markup_percent' => (float) $markupPercent,
            'fee_percent' => (float) $feePercent,
            'subtotal' => $options[0]['subtotal'],
            'total' => $options[0]['total'],
            'status' => 'pending',
        ]);
    }
}
