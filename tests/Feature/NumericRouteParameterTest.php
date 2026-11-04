<?php

use App\Models\User;

test('non-numeric model id returns 404 instead of a database error', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/teams/*')->assertNotFound();
    $this->actingAs($user)->get('/activities/abc/edit')->assertNotFound();
});
