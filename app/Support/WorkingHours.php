<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Rule 2: meetings only Mon-Fri, 09:00-18:00 Asia/Almaty, and the whole meeting must fit.
 * Pure logic, no database: easy to unit test.
 */
final class WorkingHours
{
    /** @param int[] $workdays ISO day numbers, 1 = Monday */
    public function __construct(
        private readonly string $timezone,
        private readonly string $dayStart,  // "09:00"
        private readonly string $dayEnd,    // "18:00"
        private readonly array $workdays,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('crm.timezone'),
            config('crm.workday_start'),
            config('crm.workday_end'),
            config('crm.workdays'),
        );
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    /** null when [start, end) is inside working hours; otherwise a human-readable reason. */
    public function violation(CarbonImmutable $startUtc, CarbonImmutable $endUtc): ?string
    {
        $start = $startUtc->setTimezone($this->timezone);
        $end = $endUtc->setTimezone($this->timezone);

        if (! in_array($start->dayOfWeekIso, $this->workdays, true)) {
            return sprintf(
                'Meetings are only possible Monday to Friday; %s is a %s in %s.',
                $start->format('Y-m-d'), $start->format('l'), $this->timezone
            );
        }

        $open = $start->setTimeFromTimeString($this->dayStart);
        $close = $start->setTimeFromTimeString($this->dayEnd);

        // The meeting may start exactly at opening and end exactly at closing.
        if ($start->lt($open) || $end->gt($close)) {
            return sprintf(
                'The meeting must fit within working hours %s-%s %s; requested %s-%s local time.',
                $this->dayStart, $this->dayEnd, $this->timezone, $start->format('H:i'), $end->format('H:i')
            );
        }

        return null;
    }
}
