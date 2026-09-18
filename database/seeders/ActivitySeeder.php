<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivityParticipantTime;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\Program;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $agreements = Agreement::all();
        $activityTypes = ActivityType::all();
        $programs = Program::all();

        if ($agreements->isEmpty() || $activityTypes->isEmpty() || $programs->isEmpty()) {
            $this->command?->warn('Missing agreements, activity types, or programs. Skipping activity seeding.');

            return;
        }

        $activityCount = rand(25, 30);

        for ($i = 0; $i < $activityCount; $i++) {
            $agreement = $agreements->random();
            $activityType = $activityTypes->random();

            $agreementUserIds = $agreement->users()->pluck('users.id')->toArray();
            if ($agreementUserIds === []) {
                continue;
            }

            $userId = $agreementUserIds[array_rand($agreementUserIds)];

            $activity = Activity::factory()->create([
                'user_id' => $userId,
                'engagement_date' => Carbon::now()->subDays(rand(1, 180)),
                'activity_type_id' => $activityType->id,
            ]);

            $activity->agreements()->attach($agreement->id);

            $agreementOrgs = $agreement->organizations()->pluck('organizations.id');
            $agreementStates = $agreement->states()->pluck('states.id');
            if ($agreementOrgs->isNotEmpty()) {
                $activity->organizations()->sync($agreementOrgs);
            }
            if ($agreementStates->isNotEmpty()) {
                $activity->states()->sync($agreementStates);
            }

            $programCount = rand(1, 2);
            $selectedPrograms = $programs->random(min($programCount, $programs->count()))->pluck('id');
            $activity->programs()->sync($selectedPrograms);

            $participantCount = min(rand(1, 2), count($agreementUserIds));
            $selectedParticipants = collect($agreementUserIds)->random($participantCount);
            $activity->participants()->sync($selectedParticipants);

            foreach ($selectedParticipants as $participantId) {
                ActivityParticipantTime::factory()->create([
                    'activity_id' => $activity->id,
                    'user_id' => $participantId,
                    'hours' => rand(1, 8) + (rand(0, 3) * 0.25),
                ]);
            }
        }
    }
}
