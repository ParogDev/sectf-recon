<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the flag catalogue. Logins are created with `php artisan sectf:user`,
     * never seeded: the instance is reachable over the tailnet.
     */
    public function run(): void
    {
        $this->call(FlagSeeder::class);
    }
}
