<?php

use App\Models\Certificate;
use App\Models\CertificateRequirement;
use App\Models\CertificationTool;
use App\Models\CertificationToolDimension;
use App\Models\CertificationToolDimensionOption;

test('certification tool create page loads repeater script outside row templates', function () {
    $admin = createSystemAdmin();
    $html = $this->actingAs($admin)->get(route('certification-tools.create'))->getContent();

    expect($html)->toContain('data-repeater-add');
    expect($html)->toContain("addBtn = e.target.closest('[data-repeater-add]')");

    $templateStart = strpos($html, '<template data-repeater-template>');
    $templateEnd = strpos($html, '</template>', $templateStart);
    $firstTemplate = substr($html, $templateStart, $templateEnd - $templateStart);

    expect($firstTemplate)->not->toContain("addBtn = e.target.closest('[data-repeater-add]')");

    // Repeater styles ship in app.css; neither the page nor its row templates may carry a <style> block.
    expect($html)->not->toContain('.repeater-row-card');
    expect($html)->not->toContain('<style');
});

test('certification tool edit page renders existing rows as collapsible display cards', function () {
    $admin = createSystemAdmin();
    $tool = CertificationTool::factory()->create();
    $dimension = CertificationToolDimension::factory()->for($tool, 'tool')->create(['name' => 'Phase']);
    CertificationToolDimensionOption::factory()->for($dimension, 'dimension')->count(3)->sequence(
        ['label' => 'Phase 1'],
        ['label' => 'Phase 2'],
        ['label' => 'Phase 3'],
    )->create();

    $html = $this->actingAs($admin)->get(route('certification-tools.edit', $tool))->getContent();

    // Existing dimension starts collapsed in display mode; the add-template starts in edit mode.
    expect($html)->toContain('data-repeater-mode="display"');
    expect($html)->toContain('data-repeater-mode="edit"');
    expect($html)->toContain('data-repeater-collapsible');
    expect($html)->toContain('data-repeater-sortable');

    // Dead positional sort inputs were removed; order comes from drag position.
    expect($html)->not->toContain('[sort_order]');

    expect($html)->toContain('repeater--well');
    expect($html)->toContain('repeater-add');
    expect($html)->not->toContain('data-row-done');
    expect($html)->not->toContain('repeater-empty-hint');

    // Option count is listed on the dimension summary.
    expect($html)->toContain('options</span>');
});

test('certificate edit page renders requirement rows with dimension options', function () {
    $admin = createSystemAdmin();
    $tool = CertificationTool::factory()->create();
    $dimension = CertificationToolDimension::factory()->for($tool, 'tool')->create(['name' => 'Review Mode']);
    CertificationToolDimensionOption::factory()->for($dimension, 'dimension')->count(2)->sequence(
        ['label' => 'Full'],
        ['label' => 'Partial'],
    )->create();

    $certificate = Certificate::factory()->create();
    CertificateRequirement::factory()->for($certificate)->create([
        'kind' => 'tool_submission',
        'certification_tool_id' => $tool->id,
        'label' => 'Submit reviews',
    ]);

    $html = $this->actingAs($admin)->get(route('certificates.edit', $certificate))->getContent();

    expect($html)->toContain('data-certificate-editor');
    expect($html)->toContain('data-cert-requirement="0"');
    expect($html)->toContain('Submit reviews');
    expect($html)->toContain('data-cert-options="'.$dimension->id.'"');
    expect($html)->toContain('Partial');
});
