<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\State;
use App\Models\Organization;
use App\Models\Program;
use App\Models\ContactFamily;
use App\Models\ActivityType;
use App\Models\Project;
use App\Models\Engagement;
use Carbon\Carbon;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Create States
            $states = $this->createStates();
            
            // 2. Create Users
            $users = $this->createUsers();
            
            // 3. Create Programs
            $programs = $this->createPrograms();
            
            // 4. Create Contact Families & Activity Types
            $activityTypes = $this->createContactFamiliesAndActivityTypes();
            
            // 5. Create Organizations
            $organizations = $this->createOrganizations($states);
            
            // 6. Create Projects
            $projects = $this->createProjects($states, $organizations, $users);
            
            // 7. Create Engagements
            $this->createEngagements($projects, $activityTypes, $programs);
            
            $this->command->info('Demo data seeded successfully!');
        });
    }

    private function createStates(): array
    {
        $stateNames = ['Kansas', 'Indiana', 'Louisiana', 'Connecticut', 'Massachusetts', 'Ohio'];
        $states = [];
        
        foreach ($stateNames as $name) {
            $states[] = State::firstOrCreate(['name' => $name]);
        }
        
        return $states;
    }

    private function createUsers(): array
    {
        $users = [];
        
        // Admin
        $users[] = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Sarah Johnson',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
        
        // Staff
        $users[] = User::firstOrCreate(
            ['email' => 'staff1@example.com'],
            [
                'name' => 'Michael Chen',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ]
        );
        
        $users[] = User::firstOrCreate(
            ['email' => 'staff2@example.com'],
            [
                'name' => 'Jennifer Martinez',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ]
        );
        
        // Consultants
        $users[] = User::firstOrCreate(
            ['email' => 'consultant1@example.com'],
            [
                'name' => 'David Thompson',
                'password' => Hash::make('password'),
                'role' => 'consultant',
            ]
        );
        
        $users[] = User::firstOrCreate(
            ['email' => 'consultant2@example.com'],
            [
                'name' => 'Emily Rodriguez',
                'password' => Hash::make('password'),
                'role' => 'consultant',
            ]
        );
        
        return $users;
    }

    private function createPrograms(): array
    {
        $programNames = ['MRSS', 'FOCUS', 'NWIC', 'PEARLS', 'NTTAC', 'TAN2'];
        $programs = [];
        
        foreach ($programNames as $name) {
            $programs[] = Program::firstOrCreate(
                ['name' => $name],
                ['active' => true]
            );
        }
        
        return $programs;
    }

    private function createContactFamiliesAndActivityTypes(): array
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
        
        $activityTypes = [];
        $sortOrder = 0;
        
        foreach ($taxonomyData as $familyName => $typeNames) {
            $family = ContactFamily::firstOrCreate(
                ['name' => $familyName],
                ['active' => true, 'sort_order' => $sortOrder++]
            );
            
            $typeSortOrder = 0;
            foreach ($typeNames as $typeName) {
                $activityTypes[] = ActivityType::firstOrCreate(
                    [
                        'contact_family_id' => $family->id,
                        'name' => $typeName,
                    ],
                    [
                        'active' => true,
                        'sort_order' => $typeSortOrder++,
                    ]
                );
            }
        }
        
        return $activityTypes;
    }

    private function createOrganizations(array $states): array
    {
        $orgData = [
            'Kansas Department of Children and Families',
            'Indiana Family and Social Services Administration',
            'Louisiana Department of Health',
            'Connecticut Department of Children and Families',
            'Massachusetts Department of Mental Health',
            'Ohio Department of Mental Health and Addiction Services',
            'Heartland Family Service',
            'Meridian Health Services',
            'Volunteers of America',
            'Community Health Network',
            'Northeast Behavioral Health Partnership',
            'Midwest Regional Care Coordination Network',
        ];
        
        $organizations = [];
        foreach ($orgData as $index => $name) {
            $state = $states[array_rand($states)];
            $organizations[] = Organization::firstOrCreate(
                ['name' => $name],
                ['state_id' => $state->id]
            );
        }
        
        return $organizations;
    }

    private function createProjects(array $states, array $organizations, array $users): array
    {
        $projectData = [
            ['name' => 'Kansas MRSS 2025–2026', 'state' => 'Kansas'],
            ['name' => 'Indiana FOCUS Implementation 2025', 'state' => 'Indiana'],
            ['name' => 'Louisiana PEARLS Statewide Initiative', 'state' => 'Louisiana'],
            ['name' => 'Connecticut TAN2 Technical Assistance', 'state' => 'Connecticut'],
            ['name' => 'Ohio Data & Evaluation Support', 'state' => 'Ohio'],
            ['name' => 'Massachusetts NTTAC Coaching Support', 'state' => 'Massachusetts'],
            ['name' => 'Kansas Youth Services Training Project', 'state' => 'Kansas'],
            ['name' => 'Indiana Regional Care Coordination Initiative', 'state' => 'Indiana'],
            ['name' => 'Louisiana Wraparound Implementation Support', 'state' => 'Louisiana'],
        ];
        
        $projects = [];
        foreach ($projectData as $data) {
            $state = collect($states)->firstWhere('name', $data['state']);
            
            // Find organizations in this state, or pick a random one if none exist
            $stateOrgs = collect($organizations)->where('state_id', $state->id);
            $org = $stateOrgs->isNotEmpty() ? $stateOrgs->random() : collect($organizations)->random();
            
            $project = Project::firstOrCreate(
                ['name' => $data['name']],
                [
                    'organization_id' => $org->id,
                    'state_id' => $state->id,
                    'start_date' => Carbon::now()->subMonths(rand(6, 18)),
                    'end_date' => Carbon::now()->addMonths(rand(12, 24)),
                ]
            );
            
            // Assign 2-3 users to each project
            $nonAdminUsers = collect($users)->where('role', '!=', 'admin');
            if ($nonAdminUsers->isNotEmpty()) {
                $userCount = min(rand(2, 3), $nonAdminUsers->count());
                $projectUsers = $nonAdminUsers->random($userCount)->pluck('id');
                $project->users()->syncWithoutDetaching($projectUsers);
            }
            
            $projects[] = $project;
        }
        
        return $projects;
    }

    private function createEngagements(array $projects, array $activityTypes, array $programs): void
    {
        $narratives = [
            [
                'summary' => 'Delivered a one-day engagement training to regional care coordinators focused on strengthening crisis planning practices.',
                'strengths' => 'Participants demonstrated strong engagement and requested follow-up resources.',
                'recommendations' => 'Recommend quarterly follow-up sessions to reinforce implementation fidelity.',
                'follow_up' => 'Send training materials within 48 hours. Schedule follow-up coaching call in 30 days.',
            ],
            [
                'summary' => 'Conducted virtual coaching session with program leadership to address team challenges and improve wraparound fidelity.',
                'strengths' => 'Leadership team showed openness to feedback and willingness to implement recommended changes.',
                'recommendations' => 'Continue monthly coaching sessions and consider team-level training for frontline staff.',
                'follow_up' => 'Provide sample coaching protocols and schedule next session for next month.',
            ],
            [
                'summary' => 'Facilitated quarterly learning collaborative webinar with cross-state participants on data-driven decision making.',
                'strengths' => 'High attendance and active participation in Q&A. Positive feedback on case study examples.',
                'recommendations' => 'Develop follow-up resource guide and consider regional breakout sessions.',
                'follow_up' => 'Share webinar recording and slides. Collect feedback survey responses.',
            ],
            [
                'summary' => 'Completed comprehensive fidelity assessment using WFI-EZ tool with state implementation team.',
                'strengths' => 'Team demonstrated solid understanding of wraparound principles and strong family engagement.',
                'recommendations' => 'Focus improvement efforts on documentation practices and transition planning.',
                'follow_up' => 'Prepare detailed assessment report with recommendations by end of week.',
            ],
            [
                'summary' => 'Led two-day PEARLS engagement training for new care coordinators and supervisors.',
                'strengths' => 'Excellent group dynamics and skill demonstration. Strong pre-post assessment gains.',
                'recommendations' => 'Provide refresher training in six months and ongoing supervisor coaching.',
                'follow_up' => 'Distribute certificates and training evaluation summary to program director.',
            ],
            [
                'summary' => 'Conducted CQI plan development session with agency leadership to establish data collection and analysis protocols.',
                'strengths' => 'Clear organizational commitment to quality improvement and data-informed practice.',
                'recommendations' => 'Implement quarterly data review meetings and establish feedback loops with frontline staff.',
                'follow_up' => 'Finalize CQI plan template and provide dashboard development support.',
            ],
            [
                'summary' => 'Provided technical assistance on FOCUS implementation, including scoring procedures and data interpretation.',
                'strengths' => 'Team quickly grasped scoring concepts and demonstrated strong analytical skills.',
                'recommendations' => 'Continue monthly TA calls and consider peer learning opportunities with other sites.',
                'follow_up' => 'Send updated scoring guide and schedule next TA session.',
            ],
        ];

        $engagementCount = rand(25, 30);
        
        for ($i = 0; $i < $engagementCount; $i++) {
            $project = $projects[array_rand($projects)];
            $activityType = $activityTypes[array_rand($activityTypes)];
            $narrative = $narratives[array_rand($narratives)];
            
            // Get project users
            $projectUserIds = $project->users()->pluck('users.id')->toArray();
            if (empty($projectUserIds)) {
                continue; // Skip if project has no assigned users
            }
            
            // Select engagement creator (must be from project)
            $userId = $projectUserIds[array_rand($projectUserIds)];
            
            $engagement = Engagement::create([
                'project_id' => $project->id,
                'user_id' => $userId,
                'engagement_date' => Carbon::now()->subDays(rand(1, 180)),
                'activity_type_id' => $activityType->id,
                'event_hours' => rand(1, 12) + (rand(0, 3) * 0.25),
                'prep_hours' => rand(0, 4) + (rand(0, 3) * 0.25),
                'followup_hours' => rand(0, 3) + (rand(0, 3) * 0.25),
                'participant_count' => rand(5, 50),
                'summary' => $narrative['summary'],
                'follow_up' => $narrative['follow_up'],
                'strengths' => $narrative['strengths'],
                'recommendations' => $narrative['recommendations'],
            ]);
            
            // Attach 1-2 programs
            $programCount = rand(1, 2);
            $selectedPrograms = collect($programs)->random($programCount)->pluck('id');
            $engagement->programs()->sync($selectedPrograms);
            
            // Attach 1-2 internal participants (from project users)
            $participantCount = min(rand(1, 2), count($projectUserIds));
            $selectedParticipants = collect($projectUserIds)->random($participantCount);
            $engagement->participants()->sync($selectedParticipants);
        }
    }
}
