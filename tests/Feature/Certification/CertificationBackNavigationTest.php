<?php

use App\Models\Certificate;
use App\Models\CertificationInstrument;

test('certification tool create and edit breadcrumbs link back to the tools index', function () {
    $admin = createSystemAdmin();
    $tool = CertificationInstrument::factory()->create();
    $indexUrl = route('certification-instruments.index');
    $indexCrumb = '<a href="'.$indexUrl.'" class="text-decoration-none">Tools &amp; Trainings</a>';

    $this->actingAs($admin)->get($indexUrl)->assertOk();

    $create = $this->actingAs($admin)->get(route('certification-instruments.create'));
    $create->assertOk();
    $create->assertSee($indexCrumb, false);
    $create->assertDontSee('Current Page', false);
    $create->assertSee('data-form-cancel', false);
    $create->assertSee('href="'.$indexUrl.'"', false);

    $edit = $this->actingAs($admin)->get(route('certification-instruments.edit', $tool));
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
    $create->assertSee('data-form-cancel', false);
    $create->assertSee('href="'.$indexUrl.'"', false);

    $edit = $this->actingAs($admin)->get(route('certificates.edit', $certificate));
    $edit->assertOk();
    $edit->assertSee($indexCrumb, false);
    $edit->assertDontSee('Current Page', false);
});

test('old certification tool URLs redirect permanently to instruments', function () {
    $this->actingAs(createSystemAdmin())->get('/certification-tools/7/edit')
        ->assertStatus(301)->assertRedirect('/certification-instruments/7/edit');
    $this->get('/certification-tools?search=comet')->assertRedirect('/certification-instruments?search=comet');
});
