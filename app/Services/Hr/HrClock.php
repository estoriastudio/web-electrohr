<?php

namespace App\Services\Hr;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Reloj del proceso de asistencia y nómina (HR-005). Usa la hora real en la zona
 * de config('app.timezone') (America/Mexico_City); no hay simulador de fecha.
 * El periodo de nómina va de viernes a jueves y su id es la fecha del viernes.
 */
class HrClock
{
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'));
    }

    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    public static function yesterday(): CarbonImmutable
    {
        return self::today()->subDay();
    }

    /** Viernes que abre el periodo que contiene la fecha (id del periodo: ->toDateString()). */
    public static function periodOf(CarbonInterface|string|null $date = null): CarbonImmutable
    {
        $date = $date === null
            ? self::today()
            : CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();

        // dayOfWeekIso: lunes = 1 … domingo = 7; el viernes es 5.
        return $date->subDays(($date->dayOfWeekIso - 5 + 7) % 7);
    }

    public static function isThursday(CarbonInterface|string|null $date = null): bool
    {
        $date = $date === null
            ? self::now()
            : CarbonImmutable::parse($date, config('app.timezone'));

        return $date->dayOfWeekIso === 4;
    }

    /** Minutos transcurridos desde la medianoche (10:00 = 600). */
    public static function minutes(?CarbonInterface $at = null): int
    {
        $at ??= self::now();

        return ($at->hour * 60) + $at->minute;
    }
}
