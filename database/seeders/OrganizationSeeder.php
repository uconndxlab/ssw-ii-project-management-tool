<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\State;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $orgData = [
            ['name' => 'Kansas Department of Children and Families', 'state' => 'Kansas'],
            ['name' => 'Indiana Family and Social Services Administration', 'state' => 'Indiana'],
            ['name' => 'Louisiana Department of Health', 'state' => 'Louisiana'],
            ['name' => 'Connecticut Department of Children and Families', 'state' => 'Connecticut'],
            ['name' => 'Massachusetts Department of Mental Health', 'state' => 'Massachusetts'],
            ['name' => 'Ohio Department of Mental Health and Addiction Services', 'state' => 'Ohio'],
            ['name' => 'Heartland Family Service', 'state' => null],
            ['name' => 'Meridian Health Services', 'state' => null],
            ['name' => 'Volunteers of America', 'state' => null],
            ['name' => 'Community Health Network', 'state' => null],
            ['name' => 'Northeast Behavioral Health Partnership', 'state' => null],
            ['name' => 'Midwest Regional Care Coordination Network', 'state' => null],
        ];

        $demoStates = State::query()
            ->whereIn('name', ['Kansas', 'Indiana', 'Louisiana', 'Connecticut', 'Massachusetts', 'Ohio'])
            ->get()
            ->keyBy('name');

        foreach ($orgData as $data) {
            $org = Organization::factory()->create([
                'name' => $data['name'],
            ]);

            if ($data['state'] !== null) {
                $state = $demoStates->get($data['state']);
            } else {
                $state = $demoStates->random();
            }

            if ($state !== null) {
                $org->states()->sync([$state->id]);
            }
        }
    }
}
