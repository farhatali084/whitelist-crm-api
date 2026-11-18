<?php

namespace Tests;

use App\Models\Company;
use App\Models\Manager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

/**
 * Time in tests is frozen at Tuesday 2030-01-01 00:00 UTC, so all meetings below are in the future.
 * Asia/Almaty is UTC+5: Monday 2030-01-07 10:00 local = 2030-01-07T05:00:00Z.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected const MONDAY = '2030-01-07';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2030-01-01 00:00:00');
    }

    protected function company(string $name = 'ТОО "Тест"', ?string $bin = null): Company
    {
        static $n = 0;
        $n++;

        return Company::create([
            'name' => $name,
            'bin' => $bin ?? str_pad((string) (100000000000 + $n), 12, '0', STR_PAD_LEFT),
            'phone' => '+7 701 000 00 0'.($n % 10),
        ]);
    }

    protected function manager(string $name = 'Manager'): Manager
    {
        return Manager::create(['name' => $name]);
    }

    /** UTC ISO string for a local Almaty time on the test Monday, e.g. utc('10:00') = 2030-01-07T05:00:00Z. */
    protected function utc(string $localTime, string $date = self::MONDAY): string
    {
        return \Carbon\CarbonImmutable::parse("{$date} {$localTime}", 'Asia/Almaty')->utc()->format('Y-m-d\TH:i:s\Z');
    }

    protected function book(Company $company, Manager $manager, string $startsAtUtc): TestResponse
    {
        return $this->postJson('/meetings', [
            'company_id' => $company->id,
            'manager_id' => $manager->id,
            'starts_at' => $startsAtUtc,
        ]);
    }

    /** Asserts the common error shape and returns the response for further checks. */
    protected function assertApiError(TestResponse $response, int $status, string $code): TestResponse
    {
        return $response->assertStatus($status)
            ->assertJsonPath('error.code', $code)
            ->assertJsonStructure(['error' => ['code', 'message']]);
    }
}
