<?php

namespace Database\Seeders;

use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Organization;
use App\Models\State;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AgreementSeeder extends Seeder
{
    public function run(): void
    {
        $states = State::query()
            ->whereIn('name', ['Kansas', 'Indiana', 'Louisiana', 'Connecticut', 'Massachusetts', 'Ohio'])
            ->get()
            ->keyBy('name');

        $organizations = Organization::all();
        $users = User::all();

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
            $state = $states->get($data['state']);

            $stateOrgs = $organizations->filter(
                fn (Organization $org) => $org->states->pluck('id')->contains($state?->id)
            );
            $org = $stateOrgs->isNotEmpty() ? $stateOrgs->random() : $organizations->random();

            $startDate = Carbon::now()->subMonths(rand(6, 18));
            $endDate = Carbon::now()->addMonths(rand(12, 24));

            $hasExtension = rand(0, 10) > 7;
            $extensionStartDate = null;
            $extensionEndDate = null;

            if ($hasExtension) {
                $extensionStartDate = $endDate->copy()->subMonths(rand(3, 6));
                $extensionEndDate = $endDate;
            }

            $agreement = Agreement::factory()->create([
                'name' => $data['name'],
                'abstract' => $data['abstract'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'extension_start_date' => $extensionStartDate,
                'extension_end_date' => $extensionEndDate,
            ]);

            $candidateNames = collect(preg_split('/\r\n|\r|\n/', (string) ($data['certification_candidates'] ?? '')))
                ->map(fn ($value) => trim($value))
                ->filter()
                ->values();

            foreach ($candidateNames as $candidateName) {
                AgreementCertificationCandidate::factory()->create([
                    'agreement_id' => $agreement->id,
                    'name' => $candidateName,
                ]);
            }

            $agreement->organizations()->attach($org->id);
            if ($state !== null) {
                $agreement->states()->attach($state->id);
            }

            $nonAdminUsers = $users->reject(fn (User $user) => $user->isSystemAdmin());
            if ($nonAdminUsers->isNotEmpty()) {
                $userCount = min(rand(2, 3), $nonAdminUsers->count());
                $agreement->users()->attach($nonAdminUsers->random($userCount)->pluck('id'));
            }

            $agreements[] = $agreement;
        }

        $this->attachLoggingFields($agreements);
    }

    /**
     * @param  array<int, Agreement>  $agreements
     */
    private function attachLoggingFields(array $agreements): void
    {
        $agreementLoggingFields = LoggingField::query()->where('available_in_agreements', true)->get();
        $contactFamilyLoggingFields = LoggingField::query()->where('available_in_contact_families', true)->get();

        if ($agreementLoggingFields->isEmpty() && $contactFamilyLoggingFields->isEmpty()) {
            $this->command?->warn('No logging fields found. Skipping pivot table population.');

            return;
        }

        $travelMiles = $agreementLoggingFields->firstWhere('name', 'Travel Miles');
        $materialsCost = $agreementLoggingFields->firstWhere('name', 'Materials Cost');
        $deliverablesCompleted = $agreementLoggingFields->firstWhere('name', 'Deliverables Completed');
        $outcomeNotes = $agreementLoggingFields->firstWhere('name', 'Agreement Outcome Notes');
        $deliverableType = $agreementLoggingFields->firstWhere('name', 'Deliverable Type');

        $sessionFormat = $contactFamilyLoggingFields->firstWhere('name', 'Session Format');
        $audienceSegment = $contactFamilyLoggingFields->firstWhere('name', 'Audience Segment');
        $resourceShared = $contactFamilyLoggingFields->firstWhere('name', 'Resource Shared');
        $classificationNotes = $contactFamilyLoggingFields->firstWhere('name', 'Classification Notes');

        foreach ($agreements as $index => $agreement) {
            $fieldsToAttach = [];

            if ($index % 3 === 0) {
                if ($travelMiles) {
                    $fieldsToAttach[$travelMiles->id] = ['is_required' => true];
                }
                if ($outcomeNotes) {
                    $fieldsToAttach[$outcomeNotes->id] = ['is_required' => true];
                }
                if ($deliverableType) {
                    $fieldsToAttach[$deliverableType->id] = ['is_required' => false];
                }
            } elseif ($index % 3 === 1) {
                if ($materialsCost) {
                    $fieldsToAttach[$materialsCost->id] = ['is_required' => true];
                }
                if ($outcomeNotes) {
                    $fieldsToAttach[$outcomeNotes->id] = ['is_required' => true];
                }
                if ($deliverablesCompleted) {
                    $fieldsToAttach[$deliverablesCompleted->id] = ['is_required' => false];
                }
            } else {
                if ($travelMiles) {
                    $fieldsToAttach[$travelMiles->id] = ['is_required' => false];
                }
                if ($materialsCost) {
                    $fieldsToAttach[$materialsCost->id] = ['is_required' => false];
                }
                if ($deliverablesCompleted) {
                    $fieldsToAttach[$deliverablesCompleted->id] = ['is_required' => true];
                }
            }

            if ($fieldsToAttach !== []) {
                $agreement->agreementLoggingFields()->sync($fieldsToAttach);
            }
        }

        $contactFamilies = ContactFamily::all();
        foreach ($contactFamilies as $family) {
            $fieldsToAttach = [];

            if (str_contains($family->name, 'Training')) {
                if ($sessionFormat) {
                    $fieldsToAttach[$sessionFormat->id] = ['is_required' => true];
                }
                if ($audienceSegment) {
                    $fieldsToAttach[$audienceSegment->id] = ['is_required' => true];
                }
                if ($resourceShared) {
                    $fieldsToAttach[$resourceShared->id] = ['is_required' => false];
                }
            } elseif (str_contains($family->name, 'Coaching')) {
                if ($sessionFormat) {
                    $fieldsToAttach[$sessionFormat->id] = ['is_required' => true];
                }
                if ($classificationNotes) {
                    $fieldsToAttach[$classificationNotes->id] = ['is_required' => true];
                }
            } elseif (str_contains($family->name, 'Webinar') || str_contains($family->name, 'Presentation')) {
                if ($sessionFormat) {
                    $fieldsToAttach[$sessionFormat->id] = ['is_required' => true];
                }
                if ($audienceSegment) {
                    $fieldsToAttach[$audienceSegment->id] = ['is_required' => false];
                }
                if ($resourceShared) {
                    $fieldsToAttach[$resourceShared->id] = ['is_required' => false];
                }
            } else {
                if ($audienceSegment) {
                    $fieldsToAttach[$audienceSegment->id] = ['is_required' => true];
                }
                if ($classificationNotes) {
                    $fieldsToAttach[$classificationNotes->id] = ['is_required' => false];
                }
            }

            if ($fieldsToAttach !== []) {
                $family->contactFamilyLoggingFields()->sync($fieldsToAttach);
            }
        }

        $this->command?->info('Logging fields attached to agreements and contact families.');
    }
}
