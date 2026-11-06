<?php

use App\Enums\ProgramScopeMode;
use App\Models\Activity;
use App\Models\ActivityLoggingFieldAnswer;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\AgreementDeliverable;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Organization;
use App\Models\State;
use App\Models\UserPrivilege;

test('saving unavailable fields detaches only the affected kinds and preserves answers', function (string $disabled) {
    $field = LoggingField::factory()->forAgreements()->forContactFamilies()->forActivities()->create();
    $agreement = Agreement::factory()->create(['program_scope_mode' => ProgramScopeMode::All]);
    $family = ContactFamily::factory()->create();
    $type = ActivityType::factory()->for($family)->create();
    $field->agreements()->attach($agreement);
    $field->contactFamilies()->attach($family);
    $field->activityTypes()->attach($type);
    $answer = ActivityLoggingFieldAnswer::factory()->create([
        'logging_field_id' => $field->id, 'context_type' => 'agreement', 'context_id' => $agreement->id, 'value_text' => 'Virtual',
    ]);

    $this->actingAs(createSystemAdmin())->put(route('logging-fields.update', $field), [
        'name' => $field->name, 'field_type' => 'text', 'program_scope_mode' => 'all',
        'is_active' => $disabled !== 'is_active',
        'available_in_agreements' => $disabled !== 'available_in_agreements',
        'available_in_contact_families' => $disabled !== 'available_in_contact_families',
        'available_in_activities' => $disabled !== 'available_in_activities',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($field->fresh()->{$disabled})->toBeFalse();
    expect($field->agreements()->count())->toBe(in_array($disabled, ['is_active', 'available_in_agreements']) ? 0 : 1);
    expect($field->contactFamilies()->count())->toBe(in_array($disabled, ['is_active', 'available_in_contact_families']) ? 0 : 1);
    expect($field->activityTypes()->count())->toBe(in_array($disabled, ['is_active', 'available_in_activities']) ? 0 : 1);
    $this->assertDatabaseHas('activity_logging_field_answers', ['id' => $answer->id, 'value_text' => 'Virtual']);
})->with(['available_in_agreements', 'available_in_contact_families', 'available_in_activities', 'is_active']);

test('saving field scope removes only attachments the change excludes, leaves prior mismatches and keeps answers', function (string $relation, string $mode, array $kept) {
    [$project, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $field = LoggingField::factory()->forAgreements()->forContactFamilies()->forActivities()->create([
        'program_scope_mode' => ProgramScopeMode::Specific,
    ]);
    $field->programs()->attach([$program->id, $otherProgram->id]);
    $model = match ($relation) {
        'agreements' => Agreement::class,
        'contactFamilies' => ContactFamily::class,
        'activityTypes' => ActivityType::class,
    };
    $overlapping = $model::factory()->create(['program_scope_mode' => ProgramScopeMode::Specific]);
    $overlapping->programs()->attach([$program->id, $otherProgram->id]);
    $disjoint = $model::factory()->create(['program_scope_mode' => ProgramScopeMode::Specific]);
    $disjoint->programs()->attach($otherProgram);
    $all = $model::factory()->create(['program_scope_mode' => ProgramScopeMode::All]);
    $none = $model::factory()->create(['program_scope_mode' => ProgramScopeMode::None]);
    $empty = $model::factory()->create(['program_scope_mode' => ProgramScopeMode::Specific]);
    $records = [$overlapping, $disjoint, $all, $none, $empty];
    $field->{$relation}()->attach(array_map(fn ($record) => $record->id, $records));
    $answer = ActivityLoggingFieldAnswer::factory()->create([
        'logging_field_id' => $field->id,
        'context_type' => match ($relation) {
            'agreements' => 'agreement', 'contactFamilies' => 'contact_family', 'activityTypes' => 'activity_type',
        },
        'context_id' => $disjoint->id, 'value_text' => 'Keep this answer',
    ]);

    $this->actingAs(createSystemAdmin())->put(route('logging-fields.update', $field), [
        'name' => $field->name, 'field_type' => 'text', 'program_scope_mode' => $mode,
        'project_ids' => [$project->id], 'program_ids' => [$program->id],
        'is_active' => true, 'available_in_agreements' => true,
        'available_in_contact_families' => true, 'available_in_activities' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($field->{$relation}()->pluck($records[0]->getTable().'.id')->all())
        ->toEqualCanonicalizing(array_map(fn ($index) => $records[$index]->id, $kept));
    $this->assertDatabaseHas('activity_logging_field_answers', ['id' => $answer->id, 'value_text' => 'Keep this answer']);
})->with(['agreements', 'contactFamilies', 'activityTypes'])->with([
    'narrow to one program' => ['specific', [0, 2, 3, 4]],
    'no programs' => ['none', [3, 4]],
    'all programs' => ['all', [0, 1, 2, 3, 4]],
]);

test('attached records outside edit scope lock edit update and delete without leaking hidden names', function (string $kind) {
    [, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $admin = createProgramAdmin($program);
    UserPrivilege::factory()->programViewer($otherProgram)->create(['user_id' => $admin->id]);
    $field = LoggingField::factory()->create(['program_scope_mode' => ProgramScopeMode::Specific]);
    $field->programs()->attach($program);
    $model = match ($kind) {
        'agreements' => Agreement::class,
        'contactFamilies' => ContactFamily::class,
        'activityTypes' => ActivityType::class,
    };
    $visible = $model::factory()->create(['name' => 'Visible attached record', 'program_scope_mode' => ProgramScopeMode::Specific]);
    $visible->programs()->attach($otherProgram);
    $hidden = $model::factory()->create(['name' => 'Secret attached record', 'program_scope_mode' => ProgramScopeMode::Specific]);
    $field->{$kind}()->attach([$visible->id, $hidden->id]);
    $label = match ($kind) {
        'agreements' => 'agreement', 'contactFamilies' => 'activity family', 'activityTypes' => 'activity type'
    };

    $this->actingAs($admin)->get(route('logging-fields.edit', $field))->assertForbidden();
    $this->put(route('logging-fields.update', $field), ['name' => 'Forbidden rename'])->assertForbidden();
    $this->delete(route('logging-fields.destroy', $field))->assertForbidden();
    $this->get(route('logging-fields.show', $field))->assertOk()
        ->assertSeeText("You can't edit this field because it is used on records you can't edit.", false)
        ->assertSee('Visible attached record')
        ->assertSeeText("+1 {$label} you don't have access to", false)
        ->assertDontSee('Secret attached record')
        ->assertDontSee(route('logging-fields.edit', $field));
    expect($field->fresh()->name)->toBe($field->name);
    $this->assertModelExists($field);
})->with(['agreements', 'contactFamilies', 'activityTypes']);

test('an admin who can edit every attachment can edit the field and sees warning counts', function () {
    [$project, $program] = createProjectWithProgram();
    $admin = createProgramAdmin($program);
    $field = LoggingField::factory()->forAgreements()->create(['program_scope_mode' => ProgramScopeMode::Specific]);
    $field->programs()->attach($program);
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $field->agreements()->attach($agreement);

    $this->actingAs($admin)->get(route('logging-fields.edit', $field))->assertOk()
        ->assertSee('Used on 1 agreement')->assertSee('Will be removed from 1 agreement')
        ->assertSee('data-detach-records="available_in_agreements"', false)
        ->assertSee('aria-live="polite"', false)->assertSee(route('agreements.show', $agreement))
        ->assertViewHas('attachmentGroups', function (array $groups) use ($agreement, $program) {
            $scope = ['id' => $agreement->id, 'scope_mode' => 'specific', 'program_ids' => [$program->id]];
            expect($groups['available_in_agreements']['scope_records'])->toBe([$scope]);
            expect($groups['available_in_agreements']['records'][0])->toMatchArray($scope);

            return true;
        });
    $this->put(route('logging-fields.update', $field), [
        'name' => 'Renamed field', 'field_type' => 'text', 'program_scope_mode' => 'specific',
        'project_ids' => [$project->id], 'program_ids' => [$program->id], 'is_active' => true, 'available_in_agreements' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($field->fresh()->name)->toBe('Renamed field');
    expect($field->agreements()->count())->toBe(1);
});

test('cleanup removes stale attachments and preserves eligible assignments and answers', function (string $disabled) {
    $stale = LoggingField::factory()->forAgreements()->forContactFamilies()->forActivities()->create([$disabled => false]);
    $eligible = LoggingField::factory()->forAgreements()->forContactFamilies()->forActivities()->create();
    $agreement = Agreement::factory()->create();
    $family = ContactFamily::factory()->create();
    $type = ActivityType::factory()->for($family)->create();
    foreach ([$stale, $eligible] as $field) {
        $field->agreements()->attach($agreement);
        $field->contactFamilies()->attach($family);
        $field->activityTypes()->attach($type);
    }
    $answer = ActivityLoggingFieldAnswer::factory()->create([
        'logging_field_id' => $stale->id, 'context_type' => 'agreement', 'context_id' => $agreement->id, 'value_text' => 'Keep me',
    ]);

    $migration = require database_path('migrations/2026_11_06_205912_detach_unavailable_logging_fields.php');
    $migration->up();
    $migration->down();

    expect($stale->agreements()->count())->toBe(in_array($disabled, ['is_active', 'available_in_agreements']) ? 0 : 1);
    expect($stale->contactFamilies()->count())->toBe(in_array($disabled, ['is_active', 'available_in_contact_families']) ? 0 : 1);
    expect($stale->activityTypes()->count())->toBe(in_array($disabled, ['is_active', 'available_in_activities']) ? 0 : 1);
    expect($eligible->agreements()->count())->toBe(1);
    expect($eligible->contactFamilies()->count())->toBe(1);
    expect($eligible->activityTypes()->count())->toBe(1);
    $this->assertDatabaseHas('activity_logging_field_answers', ['id' => $answer->id, 'value_text' => 'Keep me']);
})->with(['available_in_agreements', 'available_in_contact_families', 'available_in_activities', 'is_active']);

test('answers to a field removed from its agreement stay visible and editable as legacy', function () {
    [$project, $program] = createProjectWithProgram();
    $state = State::factory()->create();
    $organization = Organization::factory()->create();
    $organization->states()->attach($state);
    $contactFamily = ContactFamily::factory()->create();
    $activityType = ActivityType::factory()->for($contactFamily)->create();
    $agreement = Agreement::factory()->current()->create();
    $agreement->programs()->attach($program);
    $agreement->states()->attach($state);
    $agreement->organizations()->attach($organization->id, ['payor_source' => false, 'recipient' => false]);
    AgreementDeliverable::factory()->for($agreement)->completionByContact()->create([
        'contact_family_id' => $contactFamily->id,
        'program_id' => $program->id,
    ]);
    $field = LoggingField::factory()->forAgreements()->create(['name' => 'Session Format']);
    $agreement->agreementLoggingFields()->attach($field->id, ['is_required' => false, 'sort_order' => 0]);
    $payload = [
        'agreement_ids' => [$agreement->id],
        'state_ids' => [$state->id],
        'organization_ids' => [$organization->id],
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'engagement_date' => '2026-03-15',
        'contact_family_id' => $contactFamily->id,
        'activity_type_id' => $activityType->id,
        'completion_count' => 1,
    ];
    $this->actingAs(createSystemAdmin())
        ->post(route('activities.store'), $payload + ['agreement_logging_values' => [$agreement->id => [$field->id => 'Virtual']]])
        ->assertSessionHasNoErrors();
    $activity = Activity::query()->sole();

    $agreement->agreementLoggingFields()->detach($field->id);
    $legacyValue = fn () => $activity->fresh()->loggingFieldAnswer('agreement', $field->id, $agreement->id)?->value;

    $this->get(route('activities.show', $activity))->assertOk()->assertSeeInOrder(['Session Format', 'Legacy', 'Virtual']);
    $this->get(route('activities.edit', $activity))->assertOk()->assertSeeInOrder(['Session Format', 'Legacy', 'value="Virtual"'], false);

    $this->put(route('activities.update', $activity), $payload + ['agreement_logging_values' => [$agreement->id => [$field->id => 'In person']]])
        ->assertSessionHasNoErrors();
    expect($legacyValue())->toBe('In person');

    $this->put(route('activities.update', $activity), $payload)->assertSessionHasNoErrors();
    expect($legacyValue())->toBeNull();
    $this->get(route('activities.edit', $activity))->assertOk()->assertDontSee('Session Format');
});
