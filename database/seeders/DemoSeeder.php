<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\State;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Program;
use App\Models\ContactFamily;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\AgreementDeliverable;
use App\Models\Activity;
use App\Models\LoggingField;
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
            
            // 3. Create Project
            $project = $this->createProject();
            
            // 4. Create Programs
            $programs = $this->createPrograms($project);
            
            // 5. Create Contact Families & Activity Types
            $activityTypes = $this->createContactFamiliesAndActivityTypes();
            
            // 6. Create Organizations
            $organizations = $this->createOrganizations($states);
            
            // 7. Create Agreements
            $agreements = $this->createAgreements($states, $organizations, $users);
            
            // 7b. Attach logging fields to agreements and contact families
            $this->attachLoggingFields($agreements);
            
            // 8. Create Deliverables for Agreements
            $this->createDeliverables($agreements, $activityTypes);
            
            // 9. Create Activities
            $this->createActivities($agreements, $activityTypes, $programs);
            
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

    private function createProject()
    {
        return Project::firstOrCreate(
            ['name' => 'SSW II Project'],
            [
                'description' => 'Main project for SSW II programs',
                'active' => true,
            ]
        );
    }

    private function createPrograms($project): array
    {
        $programNames = ['MRSS', 'FOCUS', 'NWIC', 'PEARLS', 'NTTAC', 'TAN2'];
        $programs = [];
        
        foreach ($programNames as $name) {
            $programs[] = Program::firstOrCreate(
                ['name' => $name],
                [
                    'active' => true,
                    'project_id' => $project->id,
                ]
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

    private function createAgreements(array $states, array $organizations, array $users): array
    {
        $agreementData = [
            [
                'name' => 'Kansas MRSS 2025–2026',
                'state' => 'Kansas',
                'abstract' => 'Comprehensive training and technical assistance contract to support statewide implementation of Mobile Response and Stabilization Services (MRSS) across all regions.',
                'certification_candidates' => "John Smith - Regional Coordinator\nMary Johnson - Care Manager\nRobert Davis - Clinical Supervisor",
            ],
            [
                'name' => 'Indiana FOCUS Implementation 2025',
                'state' => 'Indiana',
                'abstract' => 'Multi-year implementation support for FOCUS model including supervisor training, fidelity assessments, and data infrastructure development.',
                'certification_candidates' => "Lisa Anderson\nMichael Brown\nSarah Martinez",
            ],
            [
                'name' => 'Louisiana PEARLS Statewide Initiative',
                'state' => 'Louisiana',
                'abstract' => 'Statewide rollout of PEARLS engagement model with train-the-trainer approach and ongoing coaching support for regional teams.',
                'certification_candidates' => null,
            ],
            [
                'name' => 'Connecticut TAN2 Technical Assistance',
                'state' => 'Connecticut',
                'abstract' => 'Technical assistance network support focused on cross-system collaboration and family voice integration in service planning.',
                'certification_candidates' => "Jennifer Lee\nDavid Thompson",
            ],
            [
                'name' => 'Ohio Data & Evaluation Support',
                'state' => 'Ohio',
                'abstract' => 'Comprehensive data system development and evaluation support for wraparound implementation including dashboard creation and outcome measurement.',
                'certification_candidates' => null,
            ],
            [
                'name' => 'Massachusetts NTTAC Coaching Support',
                'state' => 'Massachusetts',
                'abstract' => 'Ongoing coaching and implementation support through the National Training and Technical Assistance Center for Child and Family Mental Health.',
                'certification_candidates' => "Emily Rodriguez\nChris Wilson\nAmanda Taylor",
            ],
            [
                'name' => 'Kansas Youth Services Training Project',
                'state' => 'Kansas',
                'abstract' => 'Specialized training initiative focused on youth engagement practices and transition-age services.',
                'certification_candidates' => null,
            ],
            [
                'name' => 'Indiana Regional Care Coordination Initiative',
                'state' => 'Indiana',
                'abstract' => 'Regional implementation support for care coordination infrastructure development and team coaching.',
                'certification_candidates' => "Patricia Moore\nJames Clark",
            ],
            [
                'name' => 'Louisiana Wraparound Implementation Support',
                'state' => 'Louisiana',
                'abstract' => 'Wraparound implementation support with focus on high-fidelity practice, family partnership, and community resource development.',
                'certification_candidates' => null,
            ],
        ];
        
        $agreements = [];
        foreach ($agreementData as $data) {
            $state = collect($states)->firstWhere('name', $data['state']);
            
            // Find organizations in this state, or pick a random one if none exist
            $stateOrgs = collect($organizations)->where('state_id', $state->id);
            $org = $stateOrgs->isNotEmpty() ? $stateOrgs->random() : collect($organizations)->random();
            
            $startDate = Carbon::now()->subMonths(rand(6, 18));
            $endDate = Carbon::now()->addMonths(rand(12, 24));
            
            // Some agreements have extensions
            $hasExtension = rand(0, 10) > 7; // 30% chance
            $extensionStartDate = null;
            $extensionEndDate = null;
            
            if ($hasExtension) {
                // Original end date logic: extension starts around the original end, extends further
                $extensionStartDate = $endDate->copy()->subMonths(rand(3, 6));
                $extensionEndDate = $endDate;
            }
            
            $agreement = Agreement::firstOrCreate(
                ['name' => $data['name']],
                [
                    'abstract' => $data['abstract'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'extension_start_date' => $extensionStartDate,
                    'extension_end_date' => $extensionEndDate,
                    'certification_candidates' => $data['certification_candidates'],
                ]
            );
            
            // Attach organization and state
            $agreement->organizations()->syncWithoutDetaching([$org->id]);
            $agreement->states()->syncWithoutDetaching([$state->id]);
            
            // Assign 2-3 users to each agreement
            $nonAdminUsers = collect($users)->where('role', '!=', 'admin');
            if ($nonAdminUsers->isNotEmpty()) {
                $userCount = min(rand(2, 3), $nonAdminUsers->count());
                $agreementUsers = $nonAdminUsers->random($userCount)->pluck('id');
                $agreement->users()->syncWithoutDetaching($agreementUsers);
            }
            
            $agreements[] = $agreement;
        }
        
        return $agreements;
    }

    private function createDeliverables(array $agreements, array $activityTypes): void
    {
        $deliverableTemplates = [
            [
                'activity_type_pattern' => 'Training',
                'required_activities' => 3,
                'required_hours' => null,
                'notes' => 'Deliver three training events throughout the agreement period',
            ],
            [
                'activity_type_pattern' => 'Coaching',
                'required_activities' => null,
                'required_hours' => 10,
                'notes' => 'Provide ongoing coaching support to implementation team',
            ],
            [
                'activity_type_pattern' => 'Assessment',
                'required_activities' => 2,
                'required_hours' => null,
                'notes' => 'Complete bi-annual fidelity assessments',
            ],
            [
                'activity_type_pattern' => 'Webinar',
                'required_activities' => 4,
                'required_hours' => null,
                'notes' => 'Quarterly learning collaborative webinars',
            ],
            [
                'activity_type_pattern' => 'Data',
                'required_activities' => null,
                'required_hours' => 15,
                'notes' => 'Dashboard development and data review support',
            ],
        ];
        
        foreach ($agreements as $agreement) {
            // Each agreement gets 2-4 deliverables
            $deliverableCount = rand(2, 4);
            $selectedTemplates = collect($deliverableTemplates)->random($deliverableCount);
            
            foreach ($selectedTemplates as $template) {
                // Find a matching activity type
                $matchingType = collect($activityTypes)->first(function ($type) use ($template) {
                    return str_contains($type->name, $template['activity_type_pattern']);
                });
                
                if ($matchingType) {
                    AgreementDeliverable::create([
                        'agreement_id' => $agreement->id,
                        'activity_type_id' => $matchingType->id,
                        'contact_family_id' => $matchingType->contact_family_id,
                        'required_hours' => $template['required_hours'],
                        'required_activities' => $template['required_activities'],
                        'notes' => $template['notes'],
                    ]);
                }
            }
        }
    }

    private function createActivities(array $agreements, array $activityTypes, array $programs): void
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

        $activityCount = rand(25, 30);
        
        for ($i = 0; $i < $activityCount; $i++) {
            $agreement = $agreements[array_rand($agreements)];
            $activityType = $activityTypes[array_rand($activityTypes)];
            $narrative = $narratives[array_rand($narratives)];
            
            // Get agreement users
            $agreementUserIds = $agreement->users()->pluck('users.id')->toArray();
            if (empty($agreementUserIds)) {
                continue; // Skip if agreement has no assigned users
            }
            
            // Select activity creator (must be from agreement)
            $userId = $agreementUserIds[array_rand($agreementUserIds)];
            
            $activity = Activity::create([
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
            
            // Attach agreement
            $activity->agreements()->attach($agreement->id);
            
            // Attach organizations and states from the agreement
            $agreementOrgs = $agreement->organizations()->pluck('organizations.id');
            $agreementStates = $agreement->states()->pluck('states.id');
            if ($agreementOrgs->isNotEmpty()) {
                $activity->organizations()->sync($agreementOrgs);
            }
            if ($agreementStates->isNotEmpty()) {
                $activity->states()->sync($agreementStates);
            }
            
            // Attach 1-2 programs
            $programCount = rand(1, 2);
            $selectedPrograms = collect($programs)->random($programCount)->pluck('id');
            $activity->programs()->sync($selectedPrograms);
            
            // Attach 1-2 internal participants (from agreement users)
            $participantCount = min(rand(1, 2), count($agreementUserIds));
            $selectedParticipants = collect($agreementUserIds)->random($participantCount);
            $activity->participants()->sync($selectedParticipants);
        }
    }

    private function attachLoggingFields(array $agreements): void
    {
        $loggingFields = LoggingField::all();
        if ($loggingFields->isEmpty()) {
            $this->command->warn('No logging fields found. Skipping pivot table population.');
            return;
        }

        // Get commonly used fields
        $eventHours = $loggingFields->firstWhere('name', 'Event Hours');
        $prepHours = $loggingFields->firstWhere('name', 'Prep Hours');
        $followupHours = $loggingFields->firstWhere('name', 'Follow-up Hours');
        $participantCount = $loggingFields->firstWhere('name', 'Participant Count');
        $externalAttendees = $loggingFields->firstWhere('name', 'External Attendees');
        $summary = $loggingFields->firstWhere('name', 'Summary');
        $travelMiles = $loggingFields->firstWhere('name', 'Travel Miles');
        $materialsCost = $loggingFields->firstWhere('name', 'Materials Cost');
        $deliverablesCompleted = $loggingFields->firstWhere('name', 'Deliverables Completed');
        $eventType = $loggingFields->firstWhere('name', 'Event Type');

        // Attach logging fields to a few agreements with varied requirements
        foreach ($agreements as $index => $agreement) {
            $fieldsToAttach = [];
            
            // Different agreements have different field requirements
            if ($index % 3 === 0) {
                // Training-focused agreements (require event hours, participant count, summary)
                if ($eventHours) $fieldsToAttach[$eventHours->id] = ['is_required' => true];
                if ($prepHours) $fieldsToAttach[$prepHours->id] = ['is_required' => false];
                if ($participantCount) $fieldsToAttach[$participantCount->id] = ['is_required' => true];
                if ($summary) $fieldsToAttach[$summary->id] = ['is_required' => true];
                if ($eventType) $fieldsToAttach[$eventType->id] = ['is_required' => false];
            } elseif ($index % 3 === 1) {
                // Coaching/TA-focused agreements (require all time fields, summary, follow-up)
                if ($eventHours) $fieldsToAttach[$eventHours->id] = ['is_required' => true];
                if ($prepHours) $fieldsToAttach[$prepHours->id] = ['is_required' => true];
                if ($followupHours) $fieldsToAttach[$followupHours->id] = ['is_required' => true];
                if ($summary) $fieldsToAttach[$summary->id] = ['is_required' => true];
                if ($externalAttendees) $fieldsToAttach[$externalAttendees->id] = ['is_required' => false];
            } else {
                // Data/Evaluation-focused agreements (require summary, deliverables, materials cost)
                if ($eventHours) $fieldsToAttach[$eventHours->id] = ['is_required' => true];
                if ($summary) $fieldsToAttach[$summary->id] = ['is_required' => true];
                if ($deliverablesCompleted) $fieldsToAttach[$deliverablesCompleted->id] = ['is_required' => false];
                if ($materialsCost) $fieldsToAttach[$materialsCost->id] = ['is_required' => false];
                if ($travelMiles) $fieldsToAttach[$travelMiles->id] = ['is_required' => false];
            }
            
            if (!empty($fieldsToAttach)) {
                $agreement->loggingFields()->sync($fieldsToAttach);
            }
        }

        // Attach logging fields to Contact Families
        $contactFamilies = ContactFamily::all();
        foreach ($contactFamilies as $index => $family) {
            $fieldsToAttach = [];
            
            // Different contact families have different field requirements based on their nature
            if (str_contains($family->name, 'Training')) {
                // Training activities need event hours, participant count, and summary
                if ($eventHours) $fieldsToAttach[$eventHours->id] = ['is_required' => true];
                if ($participantCount) $fieldsToAttach[$participantCount->id] = ['is_required' => true];
                if ($summary) $fieldsToAttach[$summary->id] = ['is_required' => true];
                if ($prepHours) $fieldsToAttach[$prepHours->id] = ['is_required' => false];
            } elseif (str_contains($family->name, 'Coaching')) {
                // Coaching activities need time tracking fields
                if ($eventHours) $fieldsToAttach[$eventHours->id] = ['is_required' => true];
                if ($prepHours) $fieldsToAttach[$prepHours->id] = ['is_required' => false];
                if ($followupHours) $fieldsToAttach[$followupHours->id] = ['is_required' => false];
                if ($summary) $fieldsToAttach[$summary->id] = ['is_required' => true];
            } elseif (str_contains($family->name, 'Webinar') || str_contains($family->name, 'Presentation')) {
                // Webinars need participant count and event hours
                if ($eventHours) $fieldsToAttach[$eventHours->id] = ['is_required' => true];
                if ($participantCount) $fieldsToAttach[$participantCount->id] = ['is_required' => false];
                if ($summary) $fieldsToAttach[$summary->id] = ['is_required' => true];
                if ($externalAttendees) $fieldsToAttach[$externalAttendees->id] = ['is_required' => false];
            } else {
                // Other activities - basic requirements
                if ($eventHours) $fieldsToAttach[$eventHours->id] = ['is_required' => true];
                if ($summary) $fieldsToAttach[$summary->id] = ['is_required' => true];
            }
            
            if (!empty($fieldsToAttach)) {
                $family->loggingFields()->sync($fieldsToAttach);
            }
        }

        $this->command->info('Logging fields attached to agreements and contact families.');
    }
}
