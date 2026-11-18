<?php

namespace App\Services;

use App\Models\ExpenseItem;

class ExpenseItemCatalogService
{
    /**
     * Registers/updates a catalog entry for every "outro gasto" (item_type === 'custom')
     * row found across the given options, so it can be searched and reused later.
     * The most recently used unit_price wins; no price history is kept.
     *
     * @param  array<int, array<int, array{item_type: string, description: string, unit_price: string|float}>>  $optionItems
     */
    public static function registerFromOptions(array $optionItems): void
    {
        foreach ($optionItems as $items) {
            foreach ($items as $item) {
                if (($item['item_type'] ?? null) !== 'custom') {
                    continue;
                }

                $name = trim($item['description'] ?? '');

                if ($name === '') {
                    continue;
                }

                ExpenseItem::updateOrCreate(
                    ['name' => $name],
                    ['unit_price' => (float) $item['unit_price'], 'active' => true]
                );
            }
        }
    }
}
