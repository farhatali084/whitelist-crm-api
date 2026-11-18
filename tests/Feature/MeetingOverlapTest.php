<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Required test: overlapping meetings (rules 3 and 4). */
class MeetingOverlapTest extends TestCase
{
    public function test_manager_cannot_have_overlapping_meetings(): void
    {
        $manager = $this->manager();
        $first = $this->book($this->company(), $manager, $this->utc('10:00'))->assertCreated();

        // 10:30-11:30 and 09:30-10:30 both intersect 10:00-11:00.
        foreach (['10:30', '09:30'] as $start) {
            $this->assertApiError($this->book($this->company(), $manager, $this->utc($start)), 409, 'manager_busy')
                ->assertJsonPath('error.details.conflicting_meeting.id', $first->json('data.id'));
        }
    }

    public function test_back_to_back_meetings_are_not_a_conflict(): void
    {
        $manager = $this->manager();

        // 10:00-11:00, then 11:00-12:00 and 09:00-10:00 touch it but do not overlap.
        $this->book($this->company(), $manager, $this->utc('10:00'))->assertCreated();
        $this->book($this->company(), $manager, $this->utc('11:00'))->assertCreated();
        $this->book($this->company(), $manager, $this->utc('09:00'))->assertCreated();
    }

    public function test_company_cannot_have_two_overlapping_meetings_even_with_different_managers(): void
    {
        $company = $this->company();
        $this->book($company, $this->manager('A'), $this->utc('10:00'))->assertCreated();

        $this->assertApiError($this->book($company, $this->manager('B'), $this->utc('10:30')), 409, 'company_busy');

        // Same company later that day is fine.
        $this->book($company, $this->manager('C'), $this->utc('14:00'))->assertCreated();
    }

    public function test_cancelled_meeting_frees_the_slot(): void
    {
        $manager = $this->manager();
        $id = $this->book($this->company(), $manager, $this->utc('10:00'))->json('data.id');

        $this->patchJson("/meetings/{$id}", ['status' => 'cancelled'])->assertOk();

        $this->book($this->company(), $manager, $this->utc('10:00'))->assertCreated();
    }

    public function test_reschedule_into_an_occupied_slot_is_rejected_and_nothing_changes(): void
    {
        $manager = $this->manager();
        $this->book($this->company(), $manager, $this->utc('10:00'))->assertCreated();
        $id = $this->book($this->company(), $manager, $this->utc('14:00'))->json('data.id');

        $this->assertApiError($this->patchJson("/meetings/{$id}", ['starts_at' => $this->utc('10:30')]), 409, 'manager_busy');

        $this->getJson("/meetings/{$id}")
            ->assertJsonPath('data.starts_at', $this->utc('14:00'))
            ->assertJsonCount(1, 'data.history');
    }

    public function test_moving_a_meeting_by_half_an_hour_does_not_conflict_with_itself(): void
    {
        $id = $this->book($this->company(), $this->manager(), $this->utc('10:00'))->json('data.id');

        $this->patchJson("/meetings/{$id}", ['starts_at' => $this->utc('10:30')])
            ->assertOk()
            ->assertJsonPath('data.starts_at', $this->utc('10:30'))
            ->assertJsonPath('data.ends_at', $this->utc('11:30'));
    }
}
