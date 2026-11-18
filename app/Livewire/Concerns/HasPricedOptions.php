<?php

namespace App\Livewire\Concerns;

trait HasPricedOptions
{
    public int $activeOptionIndex = 0;

    public function switchOption(int $index): void
    {
        $this->activeOptionIndex = max(0, min(2, $index));
    }

    protected function applyMarkupAndFee(float $subtotal, float $markupPercent, float $feePercent): float
    {
        $afterMarkup = $subtotal * (1 + $markupPercent / 100);

        return ($feePercent > 0 && $feePercent < 100)
            ? round($afterMarkup / (1 - $feePercent / 100), 2)
            : round($afterMarkup, 2);
    }
}
