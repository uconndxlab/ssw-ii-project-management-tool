<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;

function pageDom(string $html): DOMXPath
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($dom);
}

test('token pickers are named comboboxes that control a listbox', function () {
    $html = $this->actingAs(createSystemAdmin())->get(route('agreements.create'))->assertOk()->getContent();
    $xpath = pageDom($html);

    $inputs = $xpath->query('//input[@data-token-search]');
    expect($inputs->length)->toBeGreaterThan(5);

    foreach ($inputs as $input) {
        expect($input->getAttribute('role'))->toBe('combobox')
            ->and($input->getAttribute('aria-expanded'))->toBe('false')
            ->and($input->getAttribute('aria-label'))->not->toBe('');

        $list = $xpath->query('//*[@id="'.$input->getAttribute('aria-controls').'"]')->item(0);
        expect($list?->getAttribute('role'))->toBe('listbox');
    }

    expect($html)->toContain('aria-labelledby="agreement-scope-project-label"');
});

test('radio groups are fieldsets with legends or named radio groups', function () {
    $admin = createSystemAdmin();

    $agreement = pageDom($this->actingAs($admin)->get(route('agreements.create'))->getContent());
    $legends = collect($agreement->query('//fieldset/legend'))->map(fn ($legend) => trim($legend->textContent));

    expect($legends)->toContain('Program Scope', 'Metric', 'Contribution Basis')
        ->and($agreement->query('//*[@role="radiogroup"][@aria-label="Time tracking"]')->length)->toBe(1);

    $user = pageDom($this->get(route('admin.users.create'))->getContent());
    expect(collect($user->query('//fieldset/legend'))->map(fn ($legend) => trim($legend->textContent)))->toContain('Role');
});

test('a field with an error links the message to its field', function () {
    $html = $this->actingAs(createSystemAdmin())
        ->from(route('agreements.create'))
        ->followingRedirects()
        ->post(route('agreements.store'), [])
        ->getContent();

    $xpath = pageDom($html);
    $errors = $xpath->query('//*[@data-field-error]');

    expect($errors->length)->toBeGreaterThan(0)
        ->and($errors->item(0)->getAttribute('id'))->toEndWith('-error');
});

test('fields with help text give it an id the control can point at', function () {
    $xpath = pageDom($this->actingAs(createSystemAdmin())->get(route('agreements.create'))->getContent());

    $help = $xpath->query('//*[@data-field-help]');

    expect($help->length)->toBeGreaterThan(0)
        ->and($help->item(0)->getAttribute('id'))->toEndWith('-help');
});

test('every index filter control has an accessible name', function (string $route) {
    $xpath = pageDom($this->actingAs(createSystemAdmin())->get(route($route))->assertOk()->getContent());

    $controls = $xpath->query('//form[@data-table-filter-form]//select | //form[@data-table-filter-form]//input[@type="text"]');

    expect($controls->length)->toBeGreaterThan(0);

    foreach ($controls as $control) {
        expect($control->getAttribute('aria-label'))->not->toBe('');
    }
})->with([
    'agreements.index', 'activities.index', 'organizations.index', 'projects.index', 'programs.index',
    'states.index', 'teams.index', 'logging-fields.index', 'admin.users.index', 'activity-types.index',
    'certificates.index', 'certification-tools.index', 'contact-families.index',
]);

test('pagination arrows and the current page are announced', function () {
    $paginator = (new LengthAwarePaginator([], 100, 20, 2))->withPath('/example');

    $html = Blade::render('<x-htmx-pagination :paginator="$paginator" target="#table" />', ['paginator' => $paginator]);

    expect($html)->toContain('aria-label="Previous page"', 'aria-label="Next page"', 'aria-current="page"', 'aria-label="Page 3"');
});

test('sort links carry the sort state used for aria-sort', function () {
    $active = Blade::render('<x-table-sort-link column="name" label="Name" sort="name" direction="desc" url="/x" />');
    $inactive = Blade::render('<x-table-sort-link column="email" label="Email" sort="name" direction="desc" url="/x" />');

    expect($active)->toContain('data-sort-state="descending"')
        ->and($inactive)->toContain('data-sort-state="none"');
});

test('forms use the capped form column', function () {
    $html = $this->actingAs(createSystemAdmin())->get(route('programs.create'))->getContent();

    expect($html)->toContain('app-form-shell')->not->toContain('col-lg-10');
});

test('pages carry a polite live region for table result announcements', function () {
    $xpath = pageDom($this->actingAs(createSystemAdmin())->get(route('states.index'))->getContent());

    $live = $xpath->query('//*[@id="app-live"]')->item(0);

    expect($live?->getAttribute('role'))->toBe('status')
        ->and($live->getAttribute('aria-live'))->toBe('polite');
});

test('the result summary is marked so it can be announced', function () {
    $paginated = Blade::render('<x-htmx-pagination :paginator="$paginator" target="#table" />', [
        'paginator' => (new LengthAwarePaginator([], 100, 20, 1))->withPath('/example'),
    ]);
    $single = Blade::render('<x-htmx-pagination :paginator="$paginator" target="#table" />', [
        'paginator' => (new LengthAwarePaginator([], 5, 20, 1))->withPath('/example'),
    ]);

    expect($paginated)->toContain('data-result-count')
        ->and($single)->toContain('data-result-count');
});
