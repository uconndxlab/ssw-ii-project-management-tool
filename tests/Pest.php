<?php

use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use App\Models\UserPrivilege;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createProjectWithProgram(): array
{
    $project = Project::factory()->create();
    $program = Program::factory()->create();
    $project->programs()->attach($program);

    return [$project, $program];
}

function createSystemAdmin(): User
{
    return User::factory()->systemAdmin()->create();
}

function createProgramAdmin(Program $program): User
{
    $user = User::factory()->adminViewer()->create();
    UserPrivilege::factory()->programAdmin($program)->create(['user_id' => $user->id]);

    return $user;
}

function createProgramViewer(Program $program): User
{
    $user = User::factory()->adminViewer()->create();
    UserPrivilege::factory()->programViewer($program)->create(['user_id' => $user->id]);

    return $user;
}
