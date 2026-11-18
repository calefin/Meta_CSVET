<?php

namespace App\Support;

use App\Models\LiturgicalSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The house's liturgical week is 4 days (Ọ̀sẹ̀), not 7. Anchored at
 * 2026-06-18 = Ọjọ́ Ọ̀sẹ̀ Ifá, per the priest. The cycle repeats every 4
 * days indefinitely in both directions from that anchor.
 *
 * On top of that, a separate cycle (Òrún Awó -> Ìsán Awó -> Ìtádógún)
 * is anchored at its own date, configurable via LiturgicalSetting
 * (admin-editable, since the priest needs to be able to move it without
 * a deploy). The anchor date IS an Òrún Awó occurrence, always an
 * Ọjọ́ Ọ̀sẹ̀ Ifá day.
 *
 * The real repeating period, Òrún Awó to the next Òrún Awó, is 16 days
 * (not 17 — 17 isn't a multiple of 4, so counting the cycle in strict
 * 17-day chunks drifts the markers off the Ọjọ́ Ọ̀sẹ̀ Ifá phase after the
 * first cycle). Relative to the anchor (offset 0 = Òrún Awó):
 *   offset 0  → Òrún Awó
 *   offset 4  → Ìsán Awó
 *   offset 12 → Ìtádógún (closes the cycle)
 *   offset 16 (= 0 mod 16) → next Òrún Awó
 * "Ciclo de 17 dias" is the traditional name for the inclusive day count
 * from Òrún Awó through Ìtádógún: display day = offset + 5 (Òrún Awó is
 * day 5, Ìsán Awó day 9), except Ìtádógún itself, which is shown as
 * day 17 (it doubles as day 1 of the next cycle's count, since the next
 * cycle's day 5 is 4 days later, matching offset 0 of the new cycle).
 */
class OseCalendar
{
    private const EPOCH = '2026-06-18';

    private const DAYS = [
        ['name' => 'Ọjọ́ Ọ̀sẹ̀ Ifá', 'orisas' => ['Ifá', 'Ajé', 'Òsùn']],
        ['name' => 'Ọjọ́ Ọ̀sẹ̀ Ogun', 'orisas' => ['Ogun', 'Èṣù']],
        ['name' => 'Ọjọ́ Ọ̀sẹ̀ Jàkuta', 'orisas' => ['Ṣàngó', 'Ọya']],
        ['name' => 'Ọjọ́ Ọ̀sẹ̀ Ọbàtálá', 'orisas' => ['Ẹgbé', 'Egúngún', 'Ọbàtálá']],
    ];

    public static function forDate(CarbonInterface|string $date): array
    {
        $target = Carbon::parse($date)->startOfDay();
        $epoch = Carbon::parse(self::EPOCH)->startOfDay();

        $diff = (int) $epoch->diffInDays($target, false);
        $index = (($diff % 4) + 4) % 4;

        $data = [
            'date' => $target,
            'index' => $index,
            ...self::DAYS[$index],
        ];

        $subName = self::subNameFor($target);
        if ($subName !== null) {
            $data['sub_name'] = $subName;
        }

        return $data;
    }

    public static function today(): array
    {
        return self::forDate(Carbon::today());
    }

    /**
     * Whether $date sits on an Ọjọ́ Ọ̀sẹ̀ Ifá day (phase 0 of the 4-day
     * Ọ̀sẹ̀ cycle) - Orun Awo must always land on one of these.
     */
    public static function isIfaPhase(CarbonInterface|string $date): bool
    {
        $target = Carbon::parse($date)->startOfDay();
        $epoch = Carbon::parse(self::EPOCH)->startOfDay();
        $diff = (int) $epoch->diffInDays($target, false);

        return ((($diff % 4) + 4) % 4) === 0;
    }

    /**
     * Orun Awo / Isan Awo / Itadogun occurrences between $start and $end
     * (inclusive), one entry per day — each is a single day.
     */
    public static function specialOccurrencesBetween(CarbonInterface $start, CarbonInterface $end): array
    {
        $anchor = Carbon::parse(LiturgicalSetting::current()->orun_anchor)->startOfDay();
        $occurrences = [];

        $cursor = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $offset = self::cycleOffset($cursor, $anchor);

            if ($offset === 0) {
                $occurrences[] = ['date' => $cursor->copy(), 'ends_on' => null, 'label' => 'Òrún Awó', 'type' => 'orun'];
            } elseif ($offset === 4) {
                $occurrences[] = ['date' => $cursor->copy(), 'ends_on' => null, 'label' => 'Ìsán Awó', 'type' => 'isan'];
            } elseif ($offset === 12) {
                $occurrences[] = ['date' => $cursor->copy(), 'ends_on' => null, 'label' => 'Ìtádógún Ifá', 'type' => 'itadogun'];
            }

            $cursor->addDay();
        }

        return $occurrences;
    }

    /**
     * The 1-17 display position within the "ciclo de 17 dias" (see class
     * docblock) — Òrún Awó is day 5, Ìsán Awó day 9, Ìtádógún day 17.
     */
    public static function cycleDay(CarbonInterface|string $date): int
    {
        $offset = self::cycleOffset(Carbon::parse($date)->startOfDay());

        return $offset === 12 ? 17 : (($offset + 4) % 16) + 1;
    }

    /**
     * Days from $date until the next occurrence of $type ('orun', 'isan',
     * or 'itadogun'). 0 if $date itself is that occurrence.
     */
    public static function daysUntil(string $type, CarbonInterface|string $date): int
    {
        $target = match ($type) {
            'orun' => 0,
            'isan' => 4,
            'itadogun' => 12,
        };

        $offset = self::cycleOffset(Carbon::parse($date)->startOfDay());

        return (($target - $offset) % 16 + 16) % 16;
    }

    private static function cycleOffset(CarbonInterface $target, ?CarbonInterface $anchor = null): int
    {
        $anchor ??= Carbon::parse(LiturgicalSetting::current()->orun_anchor)->startOfDay();
        $diff = (int) $anchor->diffInDays($target, false);

        return (($diff % 16) + 16) % 16;
    }

    private static function subNameFor(CarbonInterface $target): ?string
    {
        return match (self::cycleOffset($target)) {
            0 => 'Òrún Awó',
            4 => 'Ìsán Awó',
            12 => 'Ìtádógún Ifá',
            default => null,
        };
    }
}
