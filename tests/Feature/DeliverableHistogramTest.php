<?php

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\AgreementActivityHistory;
use App\Models\AgreementDeliverable;
use App\Models\ContactFamily;
use App\Models\DeliverableContribution;
use App\Support\DeliverableActivityHistogram;
use Carbon\Carbon;

test('histogram builds buckets for an agreement with dates', function () {
    $agreement = Agreement::factory()->current()->create();
    $contactFamily = ContactFamily::factory()->create();
    $activityType = ActivityType::factory()->for($contactFamily)->create();
    $deliverable = AgreementDeliverable::factory()->for($agreement)->completionByContact()->create([
        'contact_family_id' => $contactFamily->id,
    ]);
    $activity = Activity::factory()->for($activityType)->create([
        'engagement_date' => now(),
    ]);

    $history = AgreementActivityHistory::query()->create([
        'agreement_id' => $agreement->id,
        'activity_id' => $activity->id,
        'contact_family_id' => $contactFamily->id,
        'activity_type_id' => $activityType->id,
        'activity_date' => now()->toDateString(),
        'contribution_kind' => 'completion',
    ]);

    DeliverableContribution::query()->create([
        'agreement_activity_history_id' => $history->id,
        'agreement_deliverable_id' => $deliverable->id,
        'agreement_id' => $agreement->id,
        'activity_id' => $activity->id,
        'contribution_kind' => 'completion',
        'source_assignment_type' => 'contact',
        'counted_attribution_basis' => 'contact',
        'credited_units' => 1,
        'cancelled' => false,
    ]);

    $agreement->load(['deliverables.contributions.activityHistory']);

    $buckets = DeliverableActivityHistogram::buildAgreementBuckets($agreement);

    expect($buckets['buckets'])->not->toBeEmpty()
        ->and(collect($buckets['buckets'])->sum('count'))->toBe(1);
});

test('histogram expands the span when a contribution is outside the agreement window', function () {
    $agreement = Agreement::factory()->create([
        'start_date' => Carbon::parse('2026-03-01'),
        'end_date' => Carbon::parse('2026-03-31'),
    ]);
    $contactFamily = ContactFamily::factory()->create();
    $activityType = ActivityType::factory()->for($contactFamily)->create();
    $deliverable = AgreementDeliverable::factory()->for($agreement)->completionByContact()->create([
        'contact_family_id' => $contactFamily->id,
    ]);
    $activity = Activity::factory()->for($activityType)->create();

    $history = AgreementActivityHistory::query()->create([
        'agreement_id' => $agreement->id,
        'activity_id' => $activity->id,
        'contact_family_id' => $contactFamily->id,
        'activity_type_id' => $activityType->id,
        'activity_date' => '2026-02-15',
        'contribution_kind' => 'completion',
    ]);

    DeliverableContribution::query()->create([
        'agreement_activity_history_id' => $history->id,
        'agreement_deliverable_id' => $deliverable->id,
        'agreement_id' => $agreement->id,
        'activity_id' => $activity->id,
        'contribution_kind' => 'completion',
        'source_assignment_type' => 'contact',
        'counted_attribution_basis' => 'contact',
        'credited_units' => 1,
        'cancelled' => false,
    ]);

    $agreement->load(['deliverables.contributions.activityHistory']);

    $buckets = DeliverableActivityHistogram::buildAgreementBuckets($agreement);

    expect($buckets['buckets'][0]['start'])->toBe('2026-02-15');
});

test('histogram returns empty buckets when the agreement has no dates', function () {
    $agreement = Agreement::factory()->create([
        'start_date' => null,
        'end_date' => null,
    ]);
    $agreement->setRelation('deliverables', collect());

    $buckets = DeliverableActivityHistogram::buildAgreementBuckets($agreement);

    expect($buckets['buckets'])->toBe([])
        ->and($buckets['granularity'])->toBe('weekly');
});

test('activity identity label includes the engagement date', function () {
    $activity = Activity::factory()->create([
        'engagement_date' => Carbon::parse('2026-03-15'),
    ]);

    expect($activity->identityLabel())->toContain('Mar 15, 2026');
});
