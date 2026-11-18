<?php

namespace Tests\Feature;

use Tests\TestCase;

class CompanyAndScheduleTest extends TestCase
{
    public function test_create_company_and_reject_bad_or_duplicate_bin(): void
    {
        $body = ['name' => 'ТОО "Алматы Трейд"', 'bin' => '170340021554', 'phone' => '+7 727 123 45 67'];

        $this->postJson('/companies', $body)->assertCreated()->assertJsonPath('data.status', 'new');

        $this->assertApiError($this->postJson('/companies', $body), 422, 'validation_failed')
            ->assertJsonPath('error.details.bin.0', 'A company with this BIN already exists.');
        $this->assertApiError($this->postJson('/companies', ['bin' => '12345'] + $body), 422, 'validation_failed')
            ->assertJsonPath('error.details.bin.0', 'The BIN must be exactly 12 digits.');
    }

    public function test_list_filters_by_status_and_paginates(): void
    {
        $manager = $this->manager();
        $companies = collect(range(1, 5))->map(fn () => $this->company());
        foreach ($companies->take(3) as $c) {
            $this->postJson('/calls', ['company_id' => $c->id, 'manager_id' => $manager->id, 'result' => 'no_answer'])->assertCreated();
        }

        $this->getJson('/companies?status=no_answer&per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $companies[2]->id)
            ->assertJsonPath('meta', ['page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2]);

        $this->getJson('/companies?status=new')->assertJsonPath('meta.total', 2);

        $this->assertApiError($this->getJson('/companies?status=angry'), 422, 'validation_failed');
        $this->assertApiError($this->getJson('/companies?per_page=1000'), 422, 'validation_failed');
    }

    public function test_schedule_shows_one_managers_active_meetings_for_an_almaty_day(): void
    {
        $manager = $this->manager();
        $this->book($this->company(), $manager, $this->utc('15:00'))->assertCreated();
        $this->book($this->company(), $manager, $this->utc('09:00'))->assertCreated();
        $cancelled = $this->book($this->company(), $manager, $this->utc('12:00'))->json('data.id');
        $this->patchJson("/meetings/{$cancelled}", ['status' => 'cancelled']);
        $this->book($this->company(), $manager, $this->utc('10:00', '2030-01-08'))->assertCreated(); // next day
        $this->book($this->company(), $this->manager('Other'), $this->utc('11:00'))->assertCreated(); // other manager

        $this->getJson("/managers/{$manager->id}/schedule?date=".self::MONDAY)
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Almaty')
            ->assertJsonCount(2, 'data.meetings')
            ->assertJsonPath('data.meetings.0.starts_at', '2030-01-07T04:00:00Z')
            ->assertJsonPath('data.meetings.0.local_time', '09:00-10:00')
            ->assertJsonPath('data.meetings.1.local_time', '15:00-16:00');

        $this->assertApiError($this->getJson("/managers/{$manager->id}/schedule"), 422, 'validation_failed');
        $this->assertApiError($this->getJson("/managers/{$manager->id}/schedule?date=07.01.2030"), 422, 'validation_failed');
    }
}
