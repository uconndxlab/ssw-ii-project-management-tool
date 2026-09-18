<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed reference data required by the application.
     *
     * Reference: sail artisan migrate:fresh --seed
     * Demo examples: sail artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call([
            StateSeeder::class,
        ]);
    }
}
