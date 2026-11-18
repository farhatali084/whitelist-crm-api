<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Rule 6: moving a meeting keeps its history. Plus what can and cannot be changed. */
class MeetingHistoryTest extends TestCase
{
    public function test_reschedules_keep_the_original_slot_and_every_step(): void
    {
        $manager = $this->manager();
        $other = $this->manager('Other');
        $id = $this->book($this->company(), $manager, $this->utc('10:00'))->json('data.id');

        $this->travel(1)->hours();
        $this->patchJson("/meetings/{$id}", ['starts_at' => $this->utc('15:00')])->assertOk();
        $this->patchJson("/meetings/{$id}", ['starts_at' => $this->utc('11:00', '2030-01-08'), 'manager_id' => $other->id])
            ->assertOk()
            ->assertJsonPath('data.starts_at', $this->utc('11:00', '2030-01-08'))
            ->assertJsonPath('data.manager_id', $other->id)
            // The first booked slot and the booking moment never change.
            ->assertJsonPath('data.original_starts_at', $this->utc('10:00'))
            ->assertJsonPath('data.booked_at', '2030-01-01T00:00:00Z')
            ->assertJsonCount(3, 'data.history')
            ->assertJsonPath('data.history.0.type', 'created')
            ->assertJsonPath('data.history.1.type', 'rescheduled')
            ->assertJsonPath('data.history.1.old_starts_at', $this->utc('10:00'))
            ->assertJsonPath('data.history.1.new_starts_at', $this->utc('15:00'))
            ->assertJsonPath('data.history.1.at', '2030-01-01T01:00:00Z')
            ->assertJsonPath('data.history.2.old_manager_id', $manager->id)
            ->assertJsonPath('data.history.2.new_manager_id', $other->id);
    }

    public function test_cancel_is_recorded_and_a_cancelled_meeting_is_final(): void
    {
        $id = $this->book($this->company(), $this->manager(), $this->utc('10:00'))->json('data.id');

        $this->patchJson("/meetings/{$id}", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancelled_at', '2030-01-01T00:00:00Z')
            ->assertJsonPath('data.history.1.type', 'cancelled');

        $this->assertApiError($this->patchJson("/meetings/{$id}", ['starts_at' => $this->utc('12:00')]), 409, 'meeting_cancelled');
        $this->assertApiError($this->patchJson("/meetings/{$id}", ['status' => 'cancelled']), 409, 'meeting_cancelled');
    }

    public function test_a_meeting_that_already_started_cannot_be_moved(): void
    {
        $id = $this->book($this->company(), $this->manager(), $this->utc('10:00'))->json('data.id');

        $this->travelTo('2030-01-07 05:30:00'); // 10:30 Almaty, during the meeting

        $this->assertApiError($this->patchJson("/meetings/{$id}", ['starts_at' => $this->utc('15:00')]), 409, 'meeting_started');
    }

    public function test_patch_body_must_say_what_to_change(): void
    {
        $id = $this->book($this->company(), $this->manager(), $this->utc('10:00'))->json('data.id');

        $this->assertApiError($this->patchJson("/meetings/{$id}", []), 422, 'validation_failed');
        $this->assertApiError($this->patchJson("/meetings/{$id}", ['status' => 'scheduled']), 422, 'validation_failed');
        $this->assertApiError(
            $this->patchJson("/meetings/{$id}", ['status' => 'cancelled', 'starts_at' => $this->utc('12:00')]),
            422, 'validation_failed'
        );
    }
}
