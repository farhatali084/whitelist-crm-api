<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Rule 7: every refusal is a proper HTTP code with a clear JSON message, never a 500. */
class ErrorHandlingTest extends TestCase
{
    public function test_unknown_ids_in_the_url_return_404(): void
    {
        $this->assertApiError($this->patchJson('/meetings/999', ['status' => 'cancelled']), 404, 'not_found')
            ->assertJsonPath('error.message', 'Meeting 999 not found.');
        $this->assertApiError($this->getJson('/managers/999/schedule?date=2030-01-07'), 404, 'not_found');
        $this->assertApiError($this->getJson('/no-such-endpoint'), 404, 'not_found');
    }

    public function test_unknown_ids_in_the_body_are_validation_errors(): void
    {
        $this->assertApiError($this->postJson('/meetings', [
            'company_id' => 999, 'manager_id' => 999, 'starts_at' => '2030-01-07T05:00:00Z',
        ]), 422, 'validation_failed')->assertJsonStructure(['error' => ['details' => ['company_id', 'manager_id']]]);
    }

    public function test_bad_times_are_rejected_with_a_clear_message(): void
    {
        $company = $this->company();
        $manager = $this->manager();

        foreach (['2030-01-07T10:00:00', '07.01.2030 10:00', '2030-02-30T05:00:00Z', 'tomorrow', ''] as $bad) {
            $this->assertApiError($this->book($company, $manager, $bad), 422, 'validation_failed');
        }
        // Wrong JSON types must not crash the app either.
        $this->assertApiError($this->postJson('/meetings', [
            'company_id' => 'abc', 'manager_id' => [1], 'starts_at' => ['x'],
        ]), 422, 'validation_failed');
    }

    public function test_booking_in_the_past_is_rejected(): void
    {
        $this->assertApiError($this->book($this->company(), $this->manager(), '2029-12-31T05:00:00Z'), 422, 'starts_in_past');
    }

    public function test_wrong_method_returns_405(): void
    {
        $this->assertApiError($this->deleteJson('/companies'), 405, 'method_not_allowed');
    }
}
