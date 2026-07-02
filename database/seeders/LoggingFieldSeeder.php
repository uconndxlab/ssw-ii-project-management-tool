<?php

namespace Database\Seeders;

use App\Models\LoggingField;
use Illuminate\Database\Seeder;

class LoggingFieldSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            [
                'name' => 'Travel Miles',
                'field_type' => 'decimal',
                'help_text' => 'Miles traveled in support of this agreement activity',
                'is_active' => true,
                'sort_order' => 10,
                'available_in_agreements' => true,
            ],
            [
                'name' => 'Materials Cost',
                'field_type' => 'decimal',
                'help_text' => 'Materials or supply cost attributable to the agreement',
                'is_active' => true,
                'sort_order' => 20,
                'available_in_agreements' => true,
            ],
            [
                'name' => 'Deliverables Completed',
                'field_type' => 'checkbox',
                'help_text' => 'Confirm whether agreement deliverables were completed in this activity',
                'is_active' => true,
                'sort_order' => 30,
                'available_in_agreements' => true,
            ],
            [
                'name' => 'Agreement Outcome Notes',
                'field_type' => 'textarea',
                'help_text' => 'Agreement-focused notes or outcomes captured during this activity',
                'is_active' => true,
                'sort_order' => 40,
                'available_in_agreements' => true,
            ],
            [
                'name' => 'Deliverable Type',
                'field_type' => 'select',
                'help_text' => 'Select the agreement deliverable type emphasized by this activity',
                'options_json' => ['Training', 'Coaching', 'Planning', 'Technical Assistance', 'Reporting'],
                'is_active' => true,
                'sort_order' => 50,
                'available_in_agreements' => true,
            ],
            [
                'name' => 'Session Format',
                'field_type' => 'select',
                'help_text' => 'Format used for the selected contact family activity',
                'options_json' => ['In Person', 'Virtual', 'Hybrid'],
                'is_active' => true,
                'sort_order' => 10,
                'available_in_contact_families' => true,
            ],
            [
                'name' => 'Audience Segment',
                'field_type' => 'text',
                'help_text' => 'Primary audience segment reached by this contact family activity',
                'is_active' => true,
                'sort_order' => 20,
                'available_in_contact_families' => true,
            ],
            [
                'name' => 'Resource Shared',
                'field_type' => 'checkbox',
                'help_text' => 'Indicate whether a resource or takeaway was shared',
                'is_active' => true,
                'sort_order' => 30,
                'available_in_contact_families' => true,
            ],
            [
                'name' => 'Classification Notes',
                'field_type' => 'textarea',
                'help_text' => 'Additional classification detail specific to the contact family',
                'is_active' => true,
                'sort_order' => 40,
                'available_in_contact_families' => true,
            ],
        ];

        foreach ($fields as $field) {
            LoggingField::updateOrCreate(['name' => $field['name']], array_merge([
                'is_full_width' => false,
                'available_in_agreements' => false,
                'available_in_contact_families' => false,
                'available_in_activities' => false,
            ], $field));
        }

        $this->command->info('Logging fields seeded successfully!');
    }
}
