<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Required test: boundaries of the working day (rule 2), checked in Asia/Almaty. */
class WorkingHoursTest extends TestCase
{
    public static function slots(): array
    {
        // [UTC start sent to the API, expected HTTP status]   Almaty = UTC+5
        return [
            'Mon 09:00 local: opening, allowed' => ['2030-01-07T04:00:00Z', 201],
            'Mon 08:59 local: before opening' => ['2030-01-07T03:59:00Z', 422],
            'Mon 17:00 local: ends exactly 18:00' => ['2030-01-07T12:00:00Z', 201],
            'Mon 17:01 local: ends 18:01' => ['2030-01-07T12:01:00Z', 422],
            'Mon 17:30 local: does not fit (from the task)' => ['2030-01-07T12:30:00Z', 422],
            'Mon 18:00 local: after closing' => ['2030-01-07T13:00:00Z', 422],
            'Fri 17:00 local: last slot of the week' => ['2030-01-11T12:00:00Z', 201],
            'Sat 10:00 local: weekend' => ['2030-01-12T05:00:00Z', 422],
            'Sun 10:00 local: weekend' => ['2030-01-13T05:00:00Z', 422],
            // Valid UTC working time, but already Tuesday 00:30 in Almaty: must be judged in Almaty.
            'Mon 19:30 UTC = Tue 00:30 local' => ['2030-01-07T19:30:00Z', 422],
            // Already Monday in Almaty, but 04:30: a weekday is not enough, the hour must fit too.
            'Sun 23:30 UTC = Mon 04:30 local' => ['2030-01-06T23:30:00Z', 422],
        ];
    }

    #[DataProvider('slots')]
    public function test_working_day_boundaries(string $startsAt, int $status): void
    {
        $response = $this->book($this->company(), $this->manager(), $startsAt)->assertStatus($status);

        if ($status === 422) {
            $response->assertJsonPath('error.code', 'outside_working_hours');
        }
    }

    public function test_a_time_with_an_offset_is_converted_and_returned_in_utc(): void
    {
        $this->book($this->company(), $this->manager(), '2030-01-07T09:00:00+05:00')
            ->assertCreated()
            ->assertJsonPath('data.starts_at', '2030-01-07T04:00:00Z')
            ->assertJsonPath('data.ends_at', '2030-01-07T05:00:00Z');
    }

    public function test_reschedule_is_held_to_the_same_working_hours(): void
    {
        $id = $this->book($this->company(), $this->manager(), $this->utc('10:00'))->json('data.id');

        $this->assertApiError($this->patchJson("/meetings/{$id}", ['starts_at' => $this->utc('17:30')]), 422, 'outside_working_hours');
    }
}
