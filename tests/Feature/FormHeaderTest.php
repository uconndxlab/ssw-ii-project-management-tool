<?php

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\User;

test('form pages put Save, Cancel, and status in a sticky header and have no bottom bar', function () {
    $this->actingAs(createSystemAdmin())->get(route('agreements.create'))
        ->assertOk()
        ->assertSee('data-header-sticky', false)
        ->assertSee('data-form-save', false)
        ->assertSee('data-form-cancel', false)
        ->assertSee('data-form-status', false)
        ->assertSee('class="app-header-status"', false)
        ->assertSee('data-tip="Cancel"', false)
        ->assertSee('Create Agreement')
        ->assertDontSee('id="save-bar"', false);
});

test('list and record pages do not get the sticky bar', function () {
    $agreement = Agreement::factory()->create();

    $this->actingAs(createSystemAdmin())->get(route('agreements.index'))
        ->assertOk()
        ->assertDontSee('data-header-sticky', false)
        ->assertDontSee('data-form-save', false);

    $this->get(route('agreements.show', $agreement))
        ->assertOk()
        ->assertDontSee('data-header-sticky', false);
});

test('an edit form starts as saved with the last saved time', function () {
    $agreement = Agreement::factory()->create();

    $this->actingAs(createSystemAdmin())->get(route('agreements.edit', $agreement))
        ->assertOk()
        ->assertSee('data-initial="saved"', false)
        ->assertSee('data-saved-time="'.$agreement->updated_at->format('g:i A').'"', false)
        ->assertSee('Save Agreement');
});

test('a form shown again after validation errors starts in the error state', function () {
    $this->actingAs(createSystemAdmin())
        ->from(route('agreements.create'))
        ->followingRedirects()
        ->post(route('agreements.store'), [])
        ->assertOk()
        ->assertSee('data-initial="error"', false);
});

test('the agreement page puts Duplicate in an overflow menu to the right of Edit', function () {
    $agreement = Agreement::factory()->create();

    $html = $this->actingAs(createSystemAdmin())->get(route('agreements.show', $agreement))->assertOk()->getContent();

    expect($html)->toContain('aria-label="More actions"', 'form="agreement-duplicate-form"', 'id="agreement-duplicate-form"')
        ->and(strpos($html, 'aria-label="Edit"'))->toBeLessThan(strpos($html, 'aria-label="More actions"'));
});

test('the overflow menu is left out when the user has nothing to put in it', function () {
    $member = User::factory()->create();
    $agreement = Agreement::factory()->create();
    $agreement->users()->sync([$member->id]);

    $this->actingAs($member)->get(route('agreements.show', $agreement))
        ->assertOk()
        ->assertDontSee('aria-label="More actions"', false)
        ->assertDontSee('agreement-duplicate-form', false);
});

test('the activity page keeps Edit as the main action and moves View log and Duplicate into the menu', function () {
    $activity = Activity::factory()->create();

    $html = $this->actingAs(createSystemAdmin())->get(route('activities.show', $activity))->assertOk()->getContent();

    expect($html)->toContain('aria-label="Edit"', 'aria-label="More actions"', 'form="activity-duplicate-form"')
        ->and($html)->toContain('View log');
});

test('the status sits under the buttons in the full header and inline in the compact bar', function () {
    $html = $this->actingAs(createSystemAdmin())->get(route('agreements.create'))->getContent();

    preg_match('/<div class="app-header-bar">.*?<\/div>\s*<\/div>\s*<\/div>/s', $html, $bar);

    expect(substr_count($html, 'data-form-status'))->toBe(2)
        ->and($bar[0])->toContain('data-form-status')
        ->and(strpos($html, 'class="app-header-status"'))->toBeGreaterThan(strpos($html, 'data-header-main'));
});
