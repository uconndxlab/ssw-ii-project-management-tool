<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Seeder;

class StateSeeder extends Seeder
{
    /**
     * Seed the states table with all U.S. states and territories.
     */
    public function run(): void
    {
        $states = [
            // 50 U.S. States
            ['name' => 'Alabama', 'code' => 'AL', 'is_territory' => false],
            ['name' => 'Alaska', 'code' => 'AK', 'is_territory' => false],
            ['name' => 'Arizona', 'code' => 'AZ', 'is_territory' => false],
            ['name' => 'Arkansas', 'code' => 'AR', 'is_territory' => false],
            ['name' => 'California', 'code' => 'CA', 'is_territory' => false],
            ['name' => 'Colorado', 'code' => 'CO', 'is_territory' => false],
            ['name' => 'Connecticut', 'code' => 'CT', 'is_territory' => false],
            ['name' => 'Delaware', 'code' => 'DE', 'is_territory' => false],
            ['name' => 'Florida', 'code' => 'FL', 'is_territory' => false],
            ['name' => 'Georgia', 'code' => 'GA', 'is_territory' => false],
            ['name' => 'Hawaii', 'code' => 'HI', 'is_territory' => false],
            ['name' => 'Idaho', 'code' => 'ID', 'is_territory' => false],
            ['name' => 'Illinois', 'code' => 'IL', 'is_territory' => false],
            ['name' => 'Indiana', 'code' => 'IN', 'is_territory' => false],
            ['name' => 'Iowa', 'code' => 'IA', 'is_territory' => false],
            ['name' => 'Kansas', 'code' => 'KS', 'is_territory' => false],
            ['name' => 'Kentucky', 'code' => 'KY', 'is_territory' => false],
            ['name' => 'Louisiana', 'code' => 'LA', 'is_territory' => false],
            ['name' => 'Maine', 'code' => 'ME', 'is_territory' => false],
            ['name' => 'Maryland', 'code' => 'MD', 'is_territory' => false],
            ['name' => 'Massachusetts', 'code' => 'MA', 'is_territory' => false],
            ['name' => 'Michigan', 'code' => 'MI', 'is_territory' => false],
            ['name' => 'Minnesota', 'code' => 'MN', 'is_territory' => false],
            ['name' => 'Mississippi', 'code' => 'MS', 'is_territory' => false],
            ['name' => 'Missouri', 'code' => 'MO', 'is_territory' => false],
            ['name' => 'Montana', 'code' => 'MT', 'is_territory' => false],
            ['name' => 'Nebraska', 'code' => 'NE', 'is_territory' => false],
            ['name' => 'Nevada', 'code' => 'NV', 'is_territory' => false],
            ['name' => 'New Hampshire', 'code' => 'NH', 'is_territory' => false],
            ['name' => 'New Jersey', 'code' => 'NJ', 'is_territory' => false],
            ['name' => 'New Mexico', 'code' => 'NM', 'is_territory' => false],
            ['name' => 'New York', 'code' => 'NY', 'is_territory' => false],
            ['name' => 'North Carolina', 'code' => 'NC', 'is_territory' => false],
            ['name' => 'North Dakota', 'code' => 'ND', 'is_territory' => false],
            ['name' => 'Ohio', 'code' => 'OH', 'is_territory' => false],
            ['name' => 'Oklahoma', 'code' => 'OK', 'is_territory' => false],
            ['name' => 'Oregon', 'code' => 'OR', 'is_territory' => false],
            ['name' => 'Pennsylvania', 'code' => 'PA', 'is_territory' => false],
            ['name' => 'Rhode Island', 'code' => 'RI', 'is_territory' => false],
            ['name' => 'South Carolina', 'code' => 'SC', 'is_territory' => false],
            ['name' => 'South Dakota', 'code' => 'SD', 'is_territory' => false],
            ['name' => 'Tennessee', 'code' => 'TN', 'is_territory' => false],
            ['name' => 'Texas', 'code' => 'TX', 'is_territory' => false],
            ['name' => 'Utah', 'code' => 'UT', 'is_territory' => false],
            ['name' => 'Vermont', 'code' => 'VT', 'is_territory' => false],
            ['name' => 'Virginia', 'code' => 'VA', 'is_territory' => false],
            ['name' => 'Washington', 'code' => 'WA', 'is_territory' => false],
            ['name' => 'West Virginia', 'code' => 'WV', 'is_territory' => false],
            ['name' => 'Wisconsin', 'code' => 'WI', 'is_territory' => false],
            ['name' => 'Wyoming', 'code' => 'WY', 'is_territory' => false],

            // District of Columbia
            ['name' => 'District of Columbia', 'code' => 'DC', 'is_territory' => true],

            // Major U.S. Territories
            ['name' => 'Puerto Rico', 'code' => 'PR', 'is_territory' => true],
            ['name' => 'Guam', 'code' => 'GU', 'is_territory' => true],
            ['name' => 'U.S. Virgin Islands', 'code' => 'VI', 'is_territory' => true],
            ['name' => 'American Samoa', 'code' => 'AS', 'is_territory' => true],
            ['name' => 'Northern Mariana Islands', 'code' => 'MP', 'is_territory' => true],

            // Freely Associated States (optional but useful for comprehensive data)
            ['name' => 'Federated States of Micronesia', 'code' => 'FM', 'is_territory' => true],
            ['name' => 'Marshall Islands', 'code' => 'MH', 'is_territory' => true],
            ['name' => 'Palau', 'code' => 'PW', 'is_territory' => true],
        ];

        // Use updateOrCreate for idempotency - prevents duplicates on reruns
        // Match on name first (for existing records without codes), then use code
        foreach ($states as $state) {
            State::updateOrCreate(
                ['name' => $state['name']], // Match on unique name
                $state // Update with all fields including code
            );
        }

        $this->command->info('✓ Seeded ' . count($states) . ' states and territories');
    }
}
