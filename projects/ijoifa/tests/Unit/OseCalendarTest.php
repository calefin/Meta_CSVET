<?php

namespace Tests\Unit;

use App\Models\LiturgicalSetting;
use App\Support\OseCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OseCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_anchor_sequence_matches_the_house_calendar(): void
    {
        $this->assertSame('Ọjọ́ Ọ̀sẹ̀ Ifá', OseCalendar::forDate('2026-06-18')['name']);
        $this->assertSame('Ọjọ́ Ọ̀sẹ̀ Ogun', OseCalendar::forDate('2026-06-19')['name']);
        $this->assertSame('Ọjọ́ Ọ̀sẹ̀ Jàkuta', OseCalendar::forDate('2026-06-20')['name']);
        $this->assertSame('Ọjọ́ Ọ̀sẹ̀ Ọbàtálá', OseCalendar::forDate('2026-06-21')['name']);
    }

    public function test_cycle_repeats_every_four_days(): void
    {
        $this->assertSame('Ọjọ́ Ọ̀sẹ̀ Ifá', OseCalendar::forDate('2026-06-22')['name']);
        $this->assertSame('Ọjọ́ Ọ̀sẹ̀ Ifá', OseCalendar::forDate('2026-07-04')['name']);
        $this->assertSame('Ọjọ́ Ọ̀sẹ̀ Ọbàtálá', OseCalendar::forDate('2026-06-17')['name']);
    }

    public function test_orisas_for_each_day_match_the_house_calendar(): void
    {
        $this->assertSame(['Ifá', 'Ajé', 'Òsùn'], OseCalendar::forDate('2026-06-18')['orisas']);
        $this->assertSame(['Ogun', 'Èṣù'], OseCalendar::forDate('2026-06-19')['orisas']);
        $this->assertSame(['Ṣàngó', 'Ọya'], OseCalendar::forDate('2026-06-20')['orisas']);
        $this->assertSame(['Ẹgbé', 'Egúngún', 'Ọbàtálá'], OseCalendar::forDate('2026-06-21')['orisas']);
    }

    public function test_orun_isan_itadogun_cycle_with_default_anchor(): void
    {
        LiturgicalSetting::current()->update(['orun_anchor' => '2026-06-22']);
        LiturgicalSetting::forget();

        // Orun Awo, anchored at 2026-06-22 (offset 0 / display day 5).
        $this->assertSame('Òrún Awó', OseCalendar::forDate('2026-06-22')['sub_name']);
        $this->assertSame(5, OseCalendar::cycleDay('2026-06-22'));
        $this->assertArrayNotHasKey('sub_name', OseCalendar::forDate('2026-06-23'));

        // Isan Awo is a single day: offset 4 / display day 9.
        $this->assertSame('Ìsán Awó', OseCalendar::forDate('2026-06-26')['sub_name']);
        $this->assertSame(9, OseCalendar::cycleDay('2026-06-26'));
        $this->assertArrayNotHasKey('sub_name', OseCalendar::forDate('2026-06-27'));
        $this->assertArrayNotHasKey('sub_name', OseCalendar::forDate('2026-06-28'));
        $this->assertArrayNotHasKey('sub_name', OseCalendar::forDate('2026-06-29'));

        // Offset 8 is a silent Ọjọ́ Ọ̀sẹ̀ Ifá day (display day 13) — no marker.
        $this->assertArrayNotHasKey('sub_name', OseCalendar::forDate('2026-06-30'));
        $this->assertSame(13, OseCalendar::cycleDay('2026-06-30'));

        // Itadogun is offset 12, display day 17, closes the cycle.
        $this->assertSame('Ìtádógún Ifá', OseCalendar::forDate('2026-07-04')['sub_name']);
        $this->assertSame(17, OseCalendar::cycleDay('2026-07-04'));

        // The next Orun Awo lands 4 days after Itadogun (16-day period).
        $this->assertArrayNotHasKey('sub_name', OseCalendar::forDate('2026-07-05'));
        $this->assertArrayNotHasKey('sub_name', OseCalendar::forDate('2026-07-06'));
        $this->assertArrayNotHasKey('sub_name', OseCalendar::forDate('2026-07-07'));
        $this->assertSame('Òrún Awó', OseCalendar::forDate('2026-07-08')['sub_name']);
    }

    public function test_orun_anchor_is_configurable(): void
    {
        LiturgicalSetting::current()->update(['orun_anchor' => '2026-06-26']);
        LiturgicalSetting::forget();

        $this->assertSame('Òrún Awó', OseCalendar::forDate('2026-06-26')['sub_name']);
        // 2026-06-22 is 4 days before the new anchor — that's exactly where
        // the previous cycle's Ìtádógún falls (offset 12), not a plain day.
        $this->assertSame('Ìtádógún Ifá', OseCalendar::forDate('2026-06-22')['sub_name']);
        $this->assertArrayNotHasKey('sub_name', OseCalendar::forDate('2026-06-23'));
    }

    public function test_is_ifa_phase_detects_orun_anchor_alignment(): void
    {
        $this->assertTrue(OseCalendar::isIfaPhase('2026-06-22'));
        $this->assertFalse(OseCalendar::isIfaPhase('2026-06-23'));
    }

    public function test_special_occurrences_between_lists_the_full_cycle(): void
    {
        LiturgicalSetting::current()->update(['orun_anchor' => '2026-06-22']);
        LiturgicalSetting::forget();

        $occurrences = OseCalendar::specialOccurrencesBetween(
            \Carbon\Carbon::parse('2026-06-22'),
            \Carbon\Carbon::parse('2026-07-08')
        );

        $labels = array_map(fn ($o) => $o['date']->toDateString().':'.$o['label'], $occurrences);

        $this->assertSame([
            '2026-06-22:Òrún Awó',
            '2026-06-26:Ìsán Awó',
            '2026-07-04:Ìtádógún Ifá',
            '2026-07-08:Òrún Awó',
        ], $labels);
    }
}
