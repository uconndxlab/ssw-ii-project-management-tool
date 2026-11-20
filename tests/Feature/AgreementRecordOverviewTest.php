<?php

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\AgreementDeliverable;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;

it('shows agreement period facts in the application timezone', function (?string $start, ?string $end, ?string $extension, string $expected) {
    $this->travelTo(today(config('app.timezone'))->setDate(2026, 7, 1));
    $agreement = Agreement::factory()->create(['start_date' => $start, 'end_date' => $end, 'extension_end_date' => $extension]);

    $response = $this->actingAs(createSystemAdmin())->get(route('agreements.show', $agreement))->assertOk()
        ->assertSee($expected)->assertSee('Not assigned')->assertDontSee('YTD')->assertDontSee('Year to date');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    expect((new DOMXPath($document))->query('//nav[@aria-label="Agreement sections"]//*[contains(@class,"app-count")]')->length)->toBe(0);
})->with([
    'active' => ['2026-01-01', '2026-07-10', null, '9 days left'],
    'one day' => ['2026-01-01', '2026-07-02', null, '1 day left'],
    'today' => ['2026-01-01', '2026-07-01', null, 'Ends today'],
    'future' => ['2026-07-03', '2026-08-01', null, 'Starts in 2 days'],
    'ended' => ['2026-01-01', '2026-06-30', null, 'Ended 1 day ago'],
    'extended' => ['2026-01-01', '2026-06-30', '2026-08-01', 'extended from Jun 30, 2026'],
    'missing' => [null, null, null, 'Not set'],
]);

it('gates edit and duplicate actions and explains disabled logging', function () {
    [, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->hasAttached($program)->create(['active' => false]);
    $viewer = createProgramViewer($program);

    $this->actingAs($viewer)->get(route('agreements.show', $agreement))->assertOk()
        ->assertSee('disabled aria-describedby="agreement-inactive-reason"', false)
        ->assertSee('This agreement is inactive.')->assertDontSee('More actions')
        ->assertDontSee('href="'.route('agreements.edit', $agreement).'"', false);
    $this->actingAs(createSystemAdmin())->get(route('agreements.show', $agreement))->assertOk()
        ->assertSee('More actions')->assertSee('form="agreement-duplicate-form"', false)
        ->assertSee('href="'.route('agreements.edit', $agreement).'"', false);
});

it('renders the deliverable and about empty states', function () {
    $agreement = Agreement::factory()->create(['abstract' => null]);
    $this->actingAs(createSystemAdmin())->get(route('agreements.show', $agreement))->assertOk()
        ->assertSee('No deliverables set up.')->assertSee('No summary or files added.');
});

it('links attention rows to their expandable deliverable cards', function () {
    $this->travelTo(today()->setDate(2026, 7, 1));
    $agreement = Agreement::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $deliverable = AgreementDeliverable::factory()->for($agreement)->completionByUser()->create(['target_quantity' => 10]);

    $this->actingAs(createSystemAdmin())->get(route('agreements.show', $agreement))->assertOk()
        ->assertSee('data-deliverable-target="'.$deliverable->id.'"', false)
        ->assertSee('id="deliverable-'.$deliverable->id.'"', false)
        ->assertSee('No assignees')->assertSee('app-status--warning', false);
});

it('gates candidate name links independently from the agreement', function (bool $allowed) {
    [, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->hasAttached($program)->create();
    $roster = AgreementCertificationCandidate::factory()->for($agreement)->create();
    Gate::before(fn (User $actor, string $ability, array $arguments) => $ability === 'view' && ($arguments[0] ?? null) instanceof CertificationCandidate ? $allowed : null);

    $response = $this->actingAs(createProgramViewer($program))->get(route('agreements.show', $agreement))->assertOk()->assertSee($roster->candidate->name);
    $link = 'href="'.route('certification-candidates.show', $roster->candidate).'"';
    if ($allowed) {
        $response->assertSee($link, false);
    } else {
        $response->assertDontSee($link, false);
    }
})->with([true, false]);

it('shows restricted activity and certificate badges without exposing their names', function () {
    [, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $agreement = Agreement::factory()->hasAttached($program)->hasAttached($otherProgram)->create();
    $roster = AgreementCertificationCandidate::factory()->for($agreement)->create();
    $certificate = Certificate::factory()->forProgram($otherProgram)->create(['name' => 'Restricted certificate name']);
    CertificateEnrollment::factory()->for($certificate)->create(['certification_candidate_id' => $roster->certification_candidate_id]);
    Activity::factory()->hasAttached($agreement)->create();

    $member = User::factory()->create();
    $agreement->users()->attach($member);
    $this->actingAs($member)->get(route('agreements.show', $agreement))->assertOk()
        ->assertSee("You don't have access to 1 activity", false)->assertSee('1 restricted');
    $this->actingAs(createProgramViewer($program))->get(route('agreements.show', $agreement))->assertOk()
        ->assertSee("You don't have access to 1 certificate", false)->assertSee('1 restricted')->assertDontSee('Restricted certificate name');
});

it('shows activity-only programs without counting them as agreement programs', function () {
    [, $program] = createProjectWithProgram();
    [, $activityProgram] = createProjectWithProgram();
    $agreement = Agreement::factory()->hasAttached($program)->create();
    Activity::factory()->hasAttached($agreement)->hasAttached($activityProgram)->create();

    $response = $this->actingAs(createSystemAdmin())->get(route('agreements.show', $agreement))->assertOk()
        ->assertSee('Also logged under')->assertSee($activityProgram->name);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    expect((new DOMXPath($document))->evaluate('string(//h3[text()="Programs"]/following-sibling::span)'))->toBe('1');
});

it('applies long-list scroll and filter thresholds to every overview list', function (string $kind, int $count) {
    $agreement = Agreement::factory()->create();
    match ($kind) {
        'organizations' => $agreement->organizations()->attach(Organization::factory()->count($count)->create()),
        'staff' => $agreement->users()->attach(User::factory()->count($count)->create()),
        'candidates' => AgreementCertificationCandidate::factory()->count($count)->for($agreement)->create(),
    };
    $response = $this->actingAs(createSystemAdmin())->get(route('agreements.show', $agreement))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $root = '//*[@data-list-kind="'.$kind.'"]';
    expect($xpath->query($root.'//*[@data-ledger-scroll]')->length)->toBe($count > 6 ? 1 : 0);
    expect($xpath->query($root.'//label[text()="Filter '.$kind.'"]')->length)->toBe($count > 10 ? 1 : 0);
})->with(['organizations', 'staff', 'candidates'])->with([6, 7, 10, 11]);

it('preserves the original tabs markup when actions are absent', function (bool $hash) {
    $tabs = ['overview' => 'Overview', 'details' => 'Details'];
    $html = Blade::render('<x-section-tabs :tabs="$tabs" label="Example sections" :hash="$hash" class="mb-4"><p>Content</p></x-section-tabs>', compact('tabs', 'hash'));
    $original = <<<'BLADE'
<div data-section-tabs @if($hash) data-section-tabs-hash @endif>
    <nav class="nav nav-pills gap-4 cert-sections mb-4" aria-label="Example sections">
        @foreach($tabs as $key => $title)
            @if($hash)
                <a href="#{{ $key }}" @class(['nav-link', 'active' => $key === 'overview']) data-section-nav="{{ $key }}" aria-controls="section-{{ $key }}" @if($key === 'overview') aria-current="page" @endif>{{ $title }}</a>
            @else
                <button type="button" @class(['nav-link', 'active' => $key === 'overview']) data-section-nav="{{ $key }}" aria-controls="section-{{ $key }}" @if($key === 'overview') aria-current="page" @endif>{{ $title }}</button>
            @endif
        @endforeach
    </nav>
    <p>Content</p>
</div>
BLADE;
    $normalize = fn (string $value) => preg_replace('/>\s+</', '><', trim($value));
    expect($normalize($html))->toBe($normalize(Blade::render($original, compact('tabs', 'hash'))));
})->with([true, false]);

it('keeps certificate and candidate show tabs without a record bar', function (string $kind) {
    $record = $kind === 'certificate' ? Certificate::factory()->create() : CertificationCandidate::factory()->create();
    $route = $kind === 'certificate' ? 'certificates.show' : 'certification-candidates.show';
    $this->actingAs(createSystemAdmin())->get(route($route, $record))->assertOk()
        ->assertSee('data-section-tabs', false)->assertDontSee('data-record-bar', false);
})->with(['certificate', 'candidate']);

it('groups staff under their teams with directly added people last', function () {
    $agreement = Agreement::factory()->create();
    $member = User::factory()->create(['name' => 'Team Member']);
    $direct = User::factory()->create(['name' => 'Direct Person']);
    $agreement->users()->attach($direct);
    $agreement->teams()->attach(Team::factory()->hasAttached($member)->create(['name' => 'Delta team']));

    $response = $this->actingAs(createSystemAdmin())->get(route('agreements.show', $agreement))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $groups = (new DOMXPath($document))->query('//*[@data-list-kind="staff"]//*[@data-ledger-group]');

    expect($groups->length)->toBe(2);
    expect($groups->item(0)->textContent)->toContain('Delta team')->toContain('Team Member')->not->toContain('Direct Person');
    expect($groups->item(1)->textContent)->toContain('Added directly')->toContain('Direct Person');
});
