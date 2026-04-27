<?php

namespace Database\Seeders;

use App\Models\LoggingField;
use Illuminate\Database\Seeder;

class LoggingFieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fields = [
            [
                'name' => 'Event Hours',
                'field_type' => 'decimal',
                'help_text' => 'Hours spent during the actual event/activity',
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Prep Hours',
                'field_type' => 'decimal',
                'help_text' => 'Hours spent preparing for the activity',
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'Follow-up Hours',
                'field_type' => 'decimal',
                'help_text' => 'Hours spent on follow-up activities',
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'Participant Count',
                'field_type' => 'number',
                'help_text' => 'Number of participants in the activity',
                'is_active' => true,
                'sort_order' => 40,
            ],
            [
                'name' => 'External Attendees',
                'field_type' => 'text',
                'help_text' => 'Names or organizations of external attendees',
                'is_active' => true,
                'sort_order' => 50,
            ],
            [
                'name' => 'Summary',
                'field_type' => 'textarea',
                'help_text' => 'Summary or description of the activity',
                'is_active' => true,
                'sort_order' => 60,
            ],
            [
                'name' => 'Travel Miles',
                'field_type' => 'decimal',
                'help_text' => 'Miles traveled for this activity',
                'is_active' => true,
                'sort_order' => 70,
            ],
            [
                'name' => 'Materials Cost',
                'field_type' => 'decimal',
                'help_text' => 'Cost of materials used',
                'is_active' => true,
                'sort_order' => 80,
            ],
            [
                'name' => 'Deliverables Completed',
                'field_type' => 'checkbox',
                'help_text' => 'Check if deliverables were completed',
                'is_active' => true,
                'sort_order' => 90,
            ],
            [
                'name' => 'Event Type',
                'field_type' => 'select',
                'help_text' => 'Type of event conducted',
                'options_json' => ['Workshop', 'Training', 'Presentation', 'Meeting', 'Conference', 'Other'],
                'is_active' => true,
                'sort_order' => 100,
            ],
        ];

        foreach ($fields as $field) {
            LoggingField::create($field);
        }

        $this->command->info('Logging fields seeded successfully!');
    }
}
