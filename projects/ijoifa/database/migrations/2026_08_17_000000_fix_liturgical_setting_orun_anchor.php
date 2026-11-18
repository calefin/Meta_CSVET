<?php

use App\Models\LiturgicalSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Òrún/Ìsán/Ìtádógún cycle math had a bug: it counted the cycle in
 * strict 17-day blocks, but 17 isn't a multiple of 4 (the Ọ̀sẹ̀ week), so
 * every cycle after the first drifted the markers off the Ọjọ́ Ọ̀sẹ̀ Ifá
 * phase. OseCalendar now uses the correct 16-day period. This re-anchors
 * orun_anchor so today (2026-08-17) lands on Ìsán Awó, per the priest.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('liturgical_settings')->where('id', 1)->update([
            'orun_anchor' => '2026-08-13',
            'updated_at' => now(),
        ]);

        LiturgicalSetting::forget();
    }

    public function down(): void
    {
        DB::table('liturgical_settings')->where('id', 1)->update([
            'orun_anchor' => '2026-06-22',
            'updated_at' => now(),
        ]);

        LiturgicalSetting::forget();
    }
};
