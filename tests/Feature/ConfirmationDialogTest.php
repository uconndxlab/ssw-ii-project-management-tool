<?php

use App\Models\Agreement;
use App\Models\Project;

test('confirmation record names are escaped HTML data rather than executable JavaScript', function () {
    $name = 'O\'Reilly "Civic" <script>alert(1)</script> \\ Bridge';
    $project = Project::factory()->create(['name' => $name]);

    $response = $this->actingAs(createSystemAdmin())->get(route('projects.index'))->assertOk();
    $response->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('return confirm(', false);

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($response->getContent());
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $button = $xpath->query('//button[@form="project-actions-'.$project->id.'-delete"]')->item(0);
    $form = $dom->getElementById($button->getAttribute('form'));

    expect($button->getAttribute('data-confirm-record'))->toBe($name)
        ->and($form->getAttribute('action'))->toBe(route('projects.destroy', $project))
        ->and($xpath->query('.//input[@name="_method"]', $form)->item(0)->getAttribute('value'))->toBe('DELETE');
});

test('duplicate confirmations preserve the original POST form and explain omitted progress', function () {
    $agreement = Agreement::factory()->create();

    $response = $this->actingAs(createSystemAdmin())->get(route('agreements.show', $agreement))->assertOk();

    $response->assertSee('data-confirm-action="Duplicate agreement"', false)
        ->assertSee('Activities and logged progress will not be copied.')
        ->assertSee('action="'.route('agreements.duplicate', $agreement).'"', false)
        ->assertSee('method="POST"', false);
});
