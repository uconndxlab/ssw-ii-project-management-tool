<?php

use App\Models\Agreement;

test('duplicating an agreement persists a second row', function () {
    $admin = createSystemAdmin();
    $agreement = Agreement::factory()->current()->create(['name' => 'Source Agreement']);

    $this->actingAs($admin)
        ->post(route('agreements.duplicate', $agreement))
        ->assertRedirect();

    expect(Agreement::query()->count())->toBe(2);

    $copy = Agreement::query()->whereKeyNot($agreement->id)->first();

    expect($copy)->not->toBeNull()
        ->and($copy->name)->toBe('Source Agreement (Copy)');
});
