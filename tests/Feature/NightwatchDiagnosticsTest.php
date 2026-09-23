<?php

test('system admin can fire nightwatch diagnostics when enabled', function () {
    config([
        'nightwatch.diagnostics_enabled' => true,
        'mail.from.address' => 'noreply@example.com',
        'mail.from.name' => 'Test',
    ]);

    $this->actingAs(createSystemAdmin())
        ->get(route('admin.diagnostics.nightwatch'))
        ->assertOk()
        ->assertSee('Nightwatch diagnostics fired');
});
