<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\Project;
use Illuminate\Database\Seeder;

class ProgramStructureSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::factory()->create([
            'name' => 'SSW II Project',
            'description' => 'Main project for SSW II programs',
            'active' => true,
        ]);

        $programNames = ['MRSS', 'FOCUS', 'NWIC', 'PEARLS', 'NTTAC', 'TAN2'];

        foreach ($programNames as $name) {
            $program = Program::factory()->create([
                'name' => $name,
                'active' => true,
            ]);

            $program->projects()->attach($project);
        }
    }
}
