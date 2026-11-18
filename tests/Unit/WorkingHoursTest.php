<?php

namespace Tests\Unit;

use App\Support\WorkingHours;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/** The working-hours rule on its own: no database, no HTTP. */
class WorkingHoursTest extends TestCase
{
    private function hours(): WorkingHours
    {
        return new WorkingHours('Asia/Almaty', '09:00', '18:00', [1, 2, 3, 4, 5]);
    }

    private function violation(string $startUtc, int $minutes = 60): ?string
    {
        $start = CarbonImmutable::parse($startUtc);

        return $this->hours()->violation($start, $start->addMinutes($minutes));
    }

    public function test_meeting_must_end_by_closing_time(): void
    {
        $this->assertNull($this->violation('2030-01-07T12:00:00Z'));             // 17:00-18:00 local
        $this->assertStringContainsString('17:30-18:30', $this->violation('2030-01-07T12:30:00Z'));
    }

    public function test_reason_names_the_local_weekday(): void
    {
        $this->assertStringContainsString('Saturday', $this->violation('2030-01-12T05:00:00Z'));
    }

    public function test_other_timezone_and_hours_are_configurable(): void
    {
        $utc = new WorkingHours('UTC', '08:00', '12:00', [1, 2, 3, 4, 5, 6]);
        $start = CarbonImmutable::parse('2030-01-12T08:00:00Z'); // Saturday 08:00 UTC

        $this->assertNull($utc->violation($start, $start->addHour()));
    }
}
