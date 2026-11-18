<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class LiturgicalSetting extends Model
{
    protected $fillable = ['orun_anchor'];

    protected function casts(): array
    {
        return ['orun_anchor' => 'date'];
    }

    public static function current(): self
    {
        return Cache::remember(
            'liturgical_setting',
            3600,
            fn () => self::firstOrCreate(['id' => 1], ['orun_anchor' => '2026-06-22'])
        );
    }

    public static function forget(): void
    {
        Cache::forget('liturgical_setting');
    }
}
