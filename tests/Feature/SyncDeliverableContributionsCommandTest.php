<?php

use App\Models\Agreement;

test('sync command syncs the agreement passed by id', function () {
    $agreement = Agreement::factory()->create(['name' => 'Statewide Coaching']);

    $this->artisan('deliverable-contributions:sync', ['agreement' => (string) $agreement->id])
        ->expectsOutput("Synced contributions for agreement {$agreement->id}: Statewide Coaching")
        ->assertSuccessful();
});

test('sync command fails when the agreement id does not exist', function () {
    $this->artisan('deliverable-contributions:sync', ['agreement' => '999999'])
        ->expectsOutput('Agreement 999999 not found.')
        ->assertFailed();
});

test('sync command fails when neither an agreement id nor --all is passed', function () {
    $this->artisan('deliverable-contributions:sync')
        ->expectsOutput('Pass an agreement ID or use --all.')
        ->assertFailed();
});
