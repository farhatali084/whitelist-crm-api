<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/** The API speaks UTC ISO-8601 only: "2030-01-07T04:00:00Z". */
final class Time
{
    /** Date-time with an explicit zone: "Z" or "+05:00". A time without a zone is ambiguous and rejected. */
    public const ISO_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/';

    public static function parseUtc(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value)->utc();
    }

    public static function iso(?DateTimeInterface $value): ?string
    {
        return $value ? CarbonImmutable::instance($value)->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }
}
