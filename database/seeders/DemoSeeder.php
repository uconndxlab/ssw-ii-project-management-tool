<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    /**
     * Example dev data — fictional organizations, agreements, activities, etc.
     * Requires reference seeders (StateSeeder, LoggingFieldSeeder) to have run first.
     *
     * sail artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->call([
                UserSeeder::class,
                ProgramStructureSeeder::class,
                TaxonomySeeder::class,
                OrganizationSeeder::class,
                AgreementSeeder::class,
                ActivitySeeder::class,
            ]);

            $this->command?->info('Demo data seeded successfully!');
        });
    }
}
