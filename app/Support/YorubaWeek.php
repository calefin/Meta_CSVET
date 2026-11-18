<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The house's adapted Yoruba 7-day week, aligned to the Gregorian week
 * (Sunday-Saturday). Distinct from the 4-day liturgical Ọ̀sẹ̀ cycle in
 * OseCalendar - this is fixed cultural/dictionary data, not configurable.
 */
class YorubaWeek
{
    private const DAYS = [
        0 => ['name' => 'Ọjọ́ Àìkú', 'meaning' => 'Dia em que não se morre'],
        1 => ['name' => 'Ọjọ́ Ajé', 'meaning' => 'Dia do lucro'],
        2 => ['name' => 'Ọjọ́ Ìṣẹ́gun', 'meaning' => 'Dia da vitória'],
        3 => ['name' => 'Ọjọ́-rírú', 'meaning' => 'Dia da confusão ou do sacrifício'],
        4 => ['name' => 'Ọjọ́bọ̀', 'meaning' => 'Dia da chegada ou da criação recente'],
        5 => ['name' => 'Ọjọ́ Ẹtì', 'meaning' => 'Dia da falência'],
        6 => ['name' => 'Ọjọ́ Àbá Mẹ́ta', 'meaning' => 'O dia das três sugestões'],
    ];

    public static function forDate(CarbonInterface|string $date): array
    {
        return self::DAYS[Carbon::parse($date)->dayOfWeek];
    }

    public static function all(): array
    {
        return self::DAYS;
    }
}
