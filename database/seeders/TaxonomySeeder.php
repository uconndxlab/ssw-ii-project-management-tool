<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Models\ContactFamily;
use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $taxonomyData = [
            'Training' => [
                'MRSS Training: Engagement (1-day)',
                'MRSS Training: Crisis Planning (1-day)',
                'FOCUS Supervisor Training',
                'PEARLS Engagement (2-Day)',
            ],
            'Coaching' => [
                'Organization-Level Coaching Session (Virtual)',
                'Supervisor Coaching Session',
                'Team Coaching Session',
            ],
            'Assessment & Review' => [
                'FOCUS SRT Scoring Meeting',
                'WFI-EZ Data Collection Review',
                'Implementation Fidelity Assessment',
                'Program Readiness Review',
            ],
            'Webinar / Presentation' => [
                'Cross-State Peer Webinar',
                'Quarterly Learning Collaborative Webinar',
                'National Conference Presentation',
            ],
            'Data & Evaluation' => [
                'CQI Plan Development',
                'Data Review & Feedback Session',
                'Outcome Measures Analysis',
                'Dashboard Development Support',
            ],
        ];

        $sortOrder = 0;

        foreach ($taxonomyData as $familyName => $typeNames) {
            $family = ContactFamily::factory()->create([
                'name' => $familyName,
                'active' => true,
                'sort_order' => $sortOrder++,
            ]);

            $typeSortOrder = 0;
            foreach ($typeNames as $typeName) {
                ActivityType::factory()->create([
                    'contact_family_id' => $family->id,
                    'name' => $typeName,
                    'active' => true,
                    'sort_order' => $typeSortOrder++,
                ]);
            }
        }
    }
}
