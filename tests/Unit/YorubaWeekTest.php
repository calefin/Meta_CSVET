<?php

namespace Tests\Unit;

use App\Support\YorubaWeek;
use Tests\TestCase;

class YorubaWeekTest extends TestCase
{
    public function test_maps_each_day_of_week_correctly(): void
    {
        $this->assertSame('Ọjọ́ Àìkú', YorubaWeek::forDate('2026-06-21')['name']); // Sunday
        $this->assertSame('Ọjọ́ Ajé', YorubaWeek::forDate('2026-06-22')['name']); // Monday
        $this->assertSame('Ọjọ́ Ìṣẹ́gun', YorubaWeek::forDate('2026-06-23')['name']); // Tuesday
        $this->assertSame('Ọjọ́-rírú', YorubaWeek::forDate('2026-06-24')['name']); // Wednesday
        $this->assertSame('Ọjọ́bọ̀', YorubaWeek::forDate('2026-06-25')['name']); // Thursday
        $this->assertSame('Ọjọ́ Ẹtì', YorubaWeek::forDate('2026-06-26')['name']); // Friday
        $this->assertSame('Ọjọ́ Àbá Mẹ́ta', YorubaWeek::forDate('2026-06-27')['name']); // Saturday
    }
}
