@props([
    'fields' => collect(),
    'selectedFieldIds' => [],
    'requiredFieldIds' => [],
    'fieldIdInputName',
    'requiredInputName',
    'pickerId' => null,
])

@php
    $pickerId ??= uniqid('logging-field-assignment-picker-', false);

    $fieldData = collect($fields)
        ->map(function ($field) {
            return [
                'id' => (string) $field->id,
                'name' => (string) $field->name,
                'type_label' => (string) $field->fieldTypeLabel(),
                'description' => (string) ($field->help_text ?? ''),
                'full_width' => (bool) $field->is_full_width,
                'program_ids' => $field->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                'scope_mode' => $field->program_scope_mode?->value ?? 'all',
            ];
        })
        ->values()
        ->all();

    $normalizedSelectedFieldIds = collect($selectedFieldIds)
        ->map(fn ($id) => (string) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();

    $normalizedRequiredFieldIds = collect($requiredFieldIds)
        ->map(fn ($id) => (string) $id)
        ->filter()
        ->intersect($normalizedSelectedFieldIds)
        ->values()
        ->all();
@endphp

<div id="{{ $pickerId }}"
     class="logging-field-assignment-picker"
     data-logging-field-assignment-picker
     data-fields='@json($fieldData)'
     data-selected-field-ids='@json($normalizedSelectedFieldIds)'
     data-required-field-ids='@json($normalizedRequiredFieldIds)'
     data-field-id-input-name="{{ $fieldIdInputName }}"
     data-required-input-name="{{ $requiredInputName }}">
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="logging-field-assignment-card h-100">
                <div class="mb-3">
                    <h6 class="mb-1">Available</h6>
                    <small class="text-muted">Click a field to add it.</small>
                </div>
                <div class="list-group" data-available-list></div>
                <div class="text-muted text-center small py-4 d-none" data-available-empty-state>
                    No logging fields are available for the current program scope.
                </div>
            </div>
        </div>
        <div class="col-lg-6 logging-field-assignment-divider-column">
            <div class="logging-field-assignment-card h-100">
                <div class="mb-3">
                    <h6 class="mb-1">Selected</h6>
                    <small class="text-muted">Drag to reorder.</small>
                </div>
                <div class="list-group" data-selected-list></div>
                <div class="text-muted text-center small py-4 d-none" data-selected-empty-state>
                    No logging fields selected yet.
                </div>
            </div>
        </div>
    </div>
    <div data-hidden-inputs></div>
</div>
