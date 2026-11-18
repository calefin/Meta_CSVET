<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\RecommendedWork;

class BudgetBuilderService
{
    /**
     * Persists a Budget (and its option-0 BudgetItem rows) for a RecommendedWork.
     * The caller is responsible for validating input and building the $options
     * array (up to 3 slots: null, or ['items', 'subtotal', 'total', 'comment']).
     */
    public static function save(
        RecommendedWork $recommendedWork,
        ?int $budgetId,
        array $options,
        float $subtotal,
        float $total,
        string $markupPercent,
        string $feePercent,
        ?string $notes,
        string $status,
        ?int $adminId,
    ): Budget {
        $budget = Budget::updateOrCreate(['id' => $budgetId], [
            'recommended_work_id' => $recommendedWork->id,
            'user_id' => $recommendedWork->user_id,
            'admin_id' => $adminId,
            'markup_percent' => $markupPercent,
            'fee_percent' => $feePercent,
            'subtotal' => $subtotal,
            'total' => $total,
            'status' => $status,
            'notes' => $notes,
            'options' => $options,
            'selected_option_index' => null,
        ]);

        $budget->items()->delete();

        foreach ($options[0]['items'] ?? [] as $item) {
            $budget->items()->create([
                'item_type' => $item['item_type'],
                'item_id' => $item['item_id'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => round($item['quantity'] * $item['unit_price'], 2),
                'by_client' => $item['by_client'],
            ]);
        }

        return $budget;
    }
}
