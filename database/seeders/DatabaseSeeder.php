<?php

namespace Database\Seeders;

use App\Models\Manager;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Managers are not created through the API (not in the assignment), so three are seeded.
     * Safe to run repeatedly: `composer start` runs it on every start.
     */
    public function run(): void
    {
        foreach (['Aigerim Sadykova', 'Daniyar Omarov', 'Elena Kim'] as $id => $name) {
            Manager::query()->firstOrCreate(['id' => $id + 1], ['name' => $name]);
        }
    }
}
