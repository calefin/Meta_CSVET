<?php

namespace App\Support;

use Carbon\Carbon;

class Semester
{
    /**
     * Retorna o semestre corrente no formato "AAAA/1" ou "AAAA/2".
     * Jan-Jun = semestre 1, Jul-Dez = semestre 2.
     */
    public static function current(): string
    {
        return self::forDate(now());
    }

    public static function forDate(Carbon $date): string
    {
        $half = $date->month <= 6 ? 1 : 2;

        return "{$date->year}/{$half}";
    }
}
