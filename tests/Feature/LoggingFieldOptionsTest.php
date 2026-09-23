<?php

use App\Models\LoggingField;

test('logging field normalizes string options from seed data', function () {
    $field = LoggingField::factory()->select(['Training', 'Coaching'])->create();

    expect($field->normalizedOptions())->toBe([
        ['id' => 'Training', 'label' => 'Training'],
        ['id' => 'Coaching', 'label' => 'Coaching'],
    ]);
});

test('logging field normalizes id label option rows from the admin form', function () {
    $field = LoggingField::factory()->select([])->create([
        'options_json' => [
            ['id' => 'a', 'label' => 'Training'],
            ['id' => 'b', 'label' => 'Coaching'],
        ],
    ]);

    expect($field->normalizedOptions())->toBe([
        ['id' => 'a', 'label' => 'Training'],
        ['id' => 'b', 'label' => 'Coaching'],
    ]);
});
