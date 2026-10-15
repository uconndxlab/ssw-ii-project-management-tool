<?php

use App\Enums\ProgramScopeMode;
use App\Models\ContactFamily;

test('activity family create form defaults to specific program scope', function () {
    $admin = createSystemAdmin();

    $response = $this->actingAs($admin)->get(route('contact-families.create'));

    $response->assertOk()->assertSee('Create Activity Family');
    expect($response->getContent())->toMatch('/name="program_scope_mode"\s+value="specific"\s+checked/');
});

test('activity family create form preserves the previous scope selection', function () {
    $admin = createSystemAdmin();

    $response = $this->actingAs($admin)
        ->withSession(['_old_input' => ['program_scope_mode' => 'none']])
        ->get(route('contact-families.create'));

    $response->assertOk();
    expect($response->getContent())->toMatch('/name="program_scope_mode"\s+value="none"\s+checked/');
});

test('activity family edit form preserves its saved program scope', function (ProgramScopeMode $mode) {
    $admin = createSystemAdmin();
    $contactFamily = ContactFamily::factory()->create(['program_scope_mode' => $mode]);

    $response = $this->actingAs($admin)->get(route('contact-families.edit', $contactFamily));

    $response->assertOk()->assertSee($contactFamily->name);
    expect($response->getContent())->toMatch('/name="program_scope_mode"\s+value="'.$mode->value.'"\s+checked/');
})->with([
    'global' => ProgramScopeMode::All,
    'specific' => ProgramScopeMode::Specific,
]);
