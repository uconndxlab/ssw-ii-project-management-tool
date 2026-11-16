<?php

use App\Models\Activity;
use App\Models\ActivityCertificationCandidate;
use App\Models\ActivityType;
use App\Models\Certificate;
use App\Models\CertificateAward;
use App\Models\CertificateRequirement;
use App\Models\SubActivity;
use App\Models\UserCertificationDuty;
use App\Services\CertificateProgressService;
use App\Services\CertificationEvidenceService;
use Illuminate\Database\Eloquent\Collection;

test('certificate requirements save a sub-activity and still show it, marked inactive, after it is deactivated', function () {
    $admin = createSystemAdmin();
    $subActivity = SubActivity::factory()->create();
    $payload = certificateStorePayload(['requirements' => [[
        'kind' => 'activity_count', 'target_count' => 1,
        'activity_type_id' => $subActivity->activity_type_id, 'sub_activity_id' => $subActivity->id,
    ]]]);

    $this->actingAs($admin)->post(route('certificates.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();

    $certificate = Certificate::where('name', $payload['name'])->sole();
    expect($certificate->requirements->sole()->sub_activity_id)->toBe($subActivity->id);
    $subActivity->update(['active' => false]);
    $this->get(route('certificates.edit', $certificate))->assertOk()->assertSee($subActivity->name.' (inactive)');
});

test('certificate authoring rejects a sub-activity without its owning type', function (bool $missingType) {
    $subActivity = SubActivity::factory()->create();
    $payload = certificateStorePayload(['requirements' => [[
        'kind' => 'activity_count', 'target_count' => 1,
        'activity_type_id' => $missingType ? null : ActivityType::factory()->create()->id,
        'sub_activity_id' => $subActivity->id,
    ]]]);

    $this->actingAs(createSystemAdmin())->post(route('certificates.store'), $payload)
        ->assertSessionHasErrors(['requirements.0.sub_activity_id' => 'Choose a sub-activity belonging to the selected activity type.']);

    expect(Certificate::where('name', $payload['name'])->exists())->toBeFalse();
})->with([false, true]);

test('classification matching and progress respect optional sub-activities', function (bool $hasSubActivity) {
    $f = certificationActivityFixture();
    $subActivity = SubActivity::factory()->for($f['type'])->create();
    $other = SubActivity::factory()->for($f['type'])->create();
    $requirements = collect([null, $subActivity->id, $other->id])->map(fn ($subId) => CertificateRequirement::factory()->for($f['certificate'])->activityCount()->create([
        'contact_family_id' => $f['family']->id, 'activity_type_id' => $f['type']->id, 'sub_activity_id' => $subId,
    ]));
    $activity = Activity::factory()->create([
        'activity_type_id' => $f['type']->id, 'sub_activity_id' => $hasSubActivity ? $subActivity->id : null,
    ]);
    $activity->programs()->attach($f['program']);
    ActivityCertificationCandidate::factory()->create([
        'activity_id' => $activity->id, 'certification_candidate_id' => $f['candidate']->id, 'completion_units' => 1,
    ]);

    $matches = app(CertificationEvidenceService::class)->matchingRequirements(
        $f['candidate']->fresh(), $f['type']->id, $f['family']->id, [$f['program']->id], $activity->sub_activity_id,
    );
    expect($matches->pluck('id')->all())->toBe($hasSubActivity ? [$requirements[0]->id, $requirements[1]->id] : [$requirements[0]->id]);
    $progress = app(CertificateProgressService::class)->evaluate($f['enrollment']);
    expect($progress['requirements']->pluck('count')->all())->toBe($hasSubActivity ? [1, 1, 0] : [1, 0, 0]);
})->with([true, false]);

test('capture data includes the requirement sub-activity filter', function () {
    $f = certificationActivityFixture();
    $subActivity = SubActivity::factory()->for($f['type'])->create();
    CertificateRequirement::factory()->for($f['certificate'])->create([
        'activity_type_id' => $f['type']->id, 'sub_activity_id' => $subActivity->id,
    ]);

    $data = app(CertificationEvidenceService::class)->formData($f['actor'], new Collection([$f['agreement']]));

    expect($data['candidates'][0]['enrollments'][0]['requirements'][0]['sub_id'])->toBe($subActivity->id);
});

test('endorsement freezes requirement and evidence sub-activities in the award snapshot', function () {
    $f = certificationActivityFixture();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $f['actor']->id]);
    $f['enrollment']->update(['supervising_coach_user_id' => $f['actor']->id]);
    $subActivity = SubActivity::factory()->for($f['type'])->create();
    CertificateRequirement::factory()->for($f['certificate'])->activityCount()->create([
        'activity_type_id' => $f['type']->id, 'sub_activity_id' => $subActivity->id,
    ]);
    $activity = Activity::factory()->create(['activity_type_id' => $f['type']->id, 'sub_activity_id' => $subActivity->id]);
    $activity->programs()->attach($f['program']);
    ActivityCertificationCandidate::factory()->create([
        'activity_id' => $activity->id, 'certification_candidate_id' => $f['candidate']->id, 'completion_units' => 1,
    ]);

    $this->actingAs($f['actor'])->post(route('certificate-enrollments.endorse', $f['enrollment']), ['cycle' => 1])
        ->assertSessionHas('success');

    $requirement = CertificateAward::query()->sole()->requirements_snapshot['requirements'][0];
    expect($requirement['label'])->toBe($f['type']->name.' › '.$subActivity->name);
    expect($requirement['filters']['sub_activity'])->toBe($subActivity->name);
    expect($requirement['counted'][0]['sub_activity'])->toBe($subActivity->name);
});
