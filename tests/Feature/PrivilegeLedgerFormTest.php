<?php

use App\Models\Program;

it('numbers new privilege rows past the highest row kept after a validation error', function () {
    $program = Program::factory()->create();

    $html = $this->actingAs(createSystemAdmin())
        ->withSession(['_old_input' => ['privilege_entries' => [
            2 => ['scope_type' => 'program', 'scope_id' => $program->id, 'admin' => '0'],
        ]]])
        ->get(route('admin.users.create'))
        ->assertOk()
        ->getContent();

    // Rows 0 and 1 were removed before submitting; a new row numbered 1 would later collide with row 2.
    expect($html)->toContain('privilege_entries[2][scope_id]')
        ->and($html)->toContain('"nextIndex":3');
});
