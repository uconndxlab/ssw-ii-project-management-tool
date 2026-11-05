<?php

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Models\Activity;
use App\Models\ActivityLoggingFieldAnswer;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Organization;
use App\Models\ReportRequest;
use App\Models\User;

function agreementReportRows(string $csv): array
{
    expect($csv)->toStartWith("\xEF\xBB\xBF");
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, substr($csv, 3));
    rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, escape: '')) !== false) {
        $rows[] = $row;
    }
    fclose($stream);

    return $rows;
}

it('denies agreement editors who are not system admins a download without logging a request', function () {
    [, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->hasAttached($program)->create();
    $editor = createProgramAdmin($program);

    expect($editor->can('update', $agreement))->toBeTrue();
    $this->actingAs($editor)->post(route('agreements.reports.store', $agreement))->assertForbidden();
    $this->assertDatabaseCount('report_requests', 0);
});

it('streams every agreement activity in date order for a system admin', function () {
    $this->travelTo(now()->setDate(2026, 11, 5));
    $editor = createSystemAdmin();
    $agreement = Agreement::factory()->create(['name' => 'Texas Agreement']);
    $otherAgreement = Agreement::factory()->create(['name' => 'Another Agreement']);
    $family = ContactFamily::factory()->create(['name' => 'Training']);
    $type = ActivityType::factory()->for($family)->create(['name' => 'Workshop']);
    $logger = User::factory()->create(['name' => 'Sarah Johnson']);
    $organization = Organization::factory()->create(['name' => 'Community Center']);
    Activity::factory()->for($type)->for($logger)->hasAttached($agreement)
        ->hasAttached($otherAgreement)->hasAttached($organization)->create([
            'engagement_date' => '2026-11-04', 'completion_count' => 3,
            'internal_only' => true, 'cancelled' => true, 'not_yet_complete' => true,
        ]);
    Activity::factory()->hasAttached($agreement)->create(['engagement_date' => '2026-11-01']);
    Activity::factory()->create(['engagement_date' => '2026-10-01']);

    $response = $this->actingAs($editor)->post(route('agreements.reports.store', $agreement));
    $response->assertOk()->assertDownload('texas-agreement-activities-2026-11-05.csv')
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $rows = agreementReportRows($response->streamedContent());

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toBe([
            'Date', 'Activity family', 'Activity type', 'Agreements covered', 'Organizations',
            'Completions', 'Logged by', 'Internal only', 'Cancelled', 'Not yet complete',
        ])
        ->and($rows[1][0])->toBe('2026-11-01')
        ->and($rows[2])->toBe([
            '2026-11-04', 'Training', 'Workshop', 'Another Agreement; Texas Agreement',
            'Community Center', '3', 'Sarah Johnson', 'Yes', 'Yes', 'Yes',
        ]);
});

it('keeps the requester name and email snapshot after their profile changes', function () {
    $editor = createSystemAdmin();
    $agreement = Agreement::factory()->create();
    $name = $editor->name;
    $email = $editor->email;

    $this->actingAs($editor)->post(route('agreements.reports.store', $agreement))->assertOk();
    $editor->update(['name' => 'New name', 'email' => 'new@example.com']);
    $report = ReportRequest::query()->sole();

    expect($report->user_id)->toBe($editor->id)
        ->and($report->user_name)->toBe($name)
        ->and($report->user_email)->toBe($email)
        ->and($report->type)->toBe(ReportType::BasicActivityExport)
        ->and($report->format)->toBe(ReportFormat::Csv);
});

it('exports the union of answered fields with display values and escapes formula cells', function () {
    $editor = createSystemAdmin();
    $agreement = Agreement::factory()->create();
    $first = Activity::factory()->hasAttached($agreement)->create(['engagement_date' => '2026-11-01']);
    $second = Activity::factory()->hasAttached($agreement)->create(['engagement_date' => '2026-11-02']);
    $text = LoggingField::factory()->create(['name' => '=Notes', 'sort_order' => 1]);
    $multi = LoggingField::factory()->create([
        'name' => 'Topics', 'sort_order' => 2, 'field_type' => 'multiselect',
        'options_json' => [['id' => 'a', 'label' => 'Alpha'], ['id' => 'b', 'label' => 'Beta']],
    ]);
    $file = LoggingField::factory()->create(['name' => 'Document', 'sort_order' => 3, 'field_type' => 'document']);
    $checkbox = LoggingField::factory()->create(['name' => 'Approved', 'sort_order' => 4, 'field_type' => 'checkbox']);
    LoggingField::factory()->create(['name' => 'Unanswered']);
    ActivityLoggingFieldAnswer::factory()->for($first)->for($text)->text('=SUM(1,2)')->create();
    ActivityLoggingFieldAnswer::factory()->for($first)->for($multi)->multiselect(['a', 'b'])->create();
    ActivityLoggingFieldAnswer::factory()->for($second)->for($file)->document('private/123.pdf', 'Original.pdf')->create();
    ActivityLoggingFieldAnswer::factory()->for($second)->for($checkbox)->boolean(false)->create();

    $response = $this->actingAs($editor)->post(route('agreements.reports.store', $agreement))->assertOk();
    $rows = agreementReportRows($response->streamedContent());

    expect(array_slice($rows[0], 10))->toBe(["'=Notes", 'Topics', 'Document', 'Approved'])
        ->and(array_slice($rows[1], 10))->toBe(["'=SUM(1,2)", 'Alpha; Beta', '', ''])
        ->and(array_slice($rows[2], 10))->toBe(['', '', 'Original.pdf', 'No']);
});
