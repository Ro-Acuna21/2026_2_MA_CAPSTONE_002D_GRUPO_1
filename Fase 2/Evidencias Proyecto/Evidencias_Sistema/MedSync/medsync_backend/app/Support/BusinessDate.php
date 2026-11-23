<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Business calendar for centers operating in Chile. Stored timestamps stay UTC. */
final class BusinessDate
{
    public const TIMEZONE = 'America/Santiago';

    public static function today(): string
    {
        return CarbonImmutable::now(self::TIMEZONE)->toDateString();
    }
}
