<?php

use App\Models\Certificate;
use App\Models\CertificationTool;

test('certification tool create and edit breadcrumbs link back to the tools index', function () {
    $admin = createSystemAdmin();
    $tool = CertificationTool::factory()->create();
    $indexUrl = route('certification-tools.index');
    $indexCrumb = '<a href="'.$indexUrl.'" class="text-decoration-none">Certification Tools</a>';

    $this->actingAs($admin)->get($indexUrl)->assertOk();

    $create = $this->actingAs($admin)->get(route('certification-tools.create'));
    $create->assertOk();
    $create->assertSee($indexCrumb, false);
    $create->assertDontSee('Current Page', false);
    $create->assertSee('id="save-bar-cancel"', false);
    $create->assertSee('href="'.$indexUrl.'"', false);

    $edit = $this->actingAs($admin)->get(route('certification-tools.edit', $tool));
    $edit->assertOk();
    $edit->assertSee($indexCrumb, false);
    $edit->assertDontSee('Current Page', false);
});

test('certificate create and edit breadcrumbs link back to the certificates index', function () {
    $admin = createSystemAdmin();
    $certificate = Certificate::factory()->create();
    $indexUrl = route('certificates.index');
    $indexCrumb = '<a href="'.$indexUrl.'" class="text-decoration-none">Certificates</a>';

    $this->actingAs($admin)->get($indexUrl)->assertOk();

    $create = $this->actingAs($admin)->get(route('certificates.create'));
    $create->assertOk();
    $create->assertSee($indexCrumb, false);
    $create->assertDontSee('Current Page', false);
    $create->assertSee('id="save-bar-cancel"', false);
    $create->assertSee('href="'.$indexUrl.'"', false);

    $edit = $this->actingAs($admin)->get(route('certificates.edit', $certificate));
    $edit->assertOk();
    $edit->assertSee($indexCrumb, false);
    $edit->assertDontSee('Current Page', false);
});
