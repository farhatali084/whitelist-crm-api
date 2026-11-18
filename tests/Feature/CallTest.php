<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Rule 5: a "meeting_set" call needs a real meeting. Also: company status follows the latest call. */
class CallTest extends TestCase
{
    private function logCall(array $body)
    {
        return $this->postJson('/calls', $body);
    }

    public function test_meeting_set_without_a_meeting_is_a_validation_error(): void
    {
        $company = $this->company();
        $manager = $this->manager();

        $this->assertApiError($this->logCall([
            'company_id' => $company->id, 'manager_id' => $manager->id, 'result' => 'meeting_set',
        ]), 422, 'validation_failed')->assertJsonStructure(['error' => ['details' => ['meeting_id']]]);

        $this->assertSame('new', $company->fresh()->status); // nothing was saved
    }

    public function test_meeting_set_with_a_meeting_of_another_company_is_rejected(): void
    {
        $manager = $this->manager();
        $meetingId = $this->book($this->company(), $manager, $this->utc('10:00'))->json('data.id');

        $this->assertApiError($this->logCall([
            'company_id' => $this->company()->id, 'manager_id' => $manager->id,
            'result' => 'meeting_set', 'meeting_id' => $meetingId,
        ]), 422, 'meeting_company_mismatch');
    }

    public function test_meeting_set_with_a_cancelled_meeting_is_rejected(): void
    {
        $company = $this->company();
        $manager = $this->manager();
        $meetingId = $this->book($company, $manager, $this->utc('10:00'))->json('data.id');
        $this->patchJson("/meetings/{$meetingId}", ['status' => 'cancelled'])->assertOk();

        $this->assertApiError($this->logCall([
            'company_id' => $company->id, 'manager_id' => $manager->id,
            'result' => 'meeting_set', 'meeting_id' => $meetingId,
        ]), 422, 'meeting_not_active');
    }

    public function test_valid_meeting_set_call_is_saved_and_updates_company_status(): void
    {
        $company = $this->company();
        $manager = $this->manager();
        $meetingId = $this->book($company, $manager, $this->utc('10:00'))->json('data.id');
        $body = ['company_id' => $company->id, 'manager_id' => $manager->id, 'result' => 'meeting_set', 'meeting_id' => $meetingId];

        $this->logCall($body)->assertCreated()->assertJsonPath('data.meeting_id', $meetingId);
        $this->assertSame('meeting_set', $company->fresh()->status);

        // The same meeting cannot be claimed by a second call.
        $this->assertApiError($this->logCall($body), 422, 'meeting_already_linked');
    }

    public function test_other_results_must_not_carry_a_meeting(): void
    {
        $company = $this->company();
        $manager = $this->manager();
        $meetingId = $this->book($company, $manager, $this->utc('10:00'))->json('data.id');

        $this->assertApiError($this->logCall([
            'company_id' => $company->id, 'manager_id' => $manager->id, 'result' => 'refused', 'meeting_id' => $meetingId,
        ]), 422, 'validation_failed');

        $this->logCall(['company_id' => $company->id, 'manager_id' => $manager->id, 'result' => 'no_answer'])->assertCreated();
        $this->assertSame('no_answer', $company->fresh()->status);
    }
}
