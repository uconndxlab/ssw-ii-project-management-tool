@php
    $field = $agreementLoggingField ?? $contactFamilyLoggingField ?? $loggingField ?? null;
    $availabilityOptions = $availabilityOptions ?? \App\Models\LoggingField::availabilityOptions();
    $selectedFieldType = old('field_type', $field?->field_type ?? '');

    if ($selectedFieldType === \App\Models\LoggingField::FIELD_TYPE_CHECKBOX_GROUP) {
        $selectedFieldType = \App\Models\LoggingField::FIELD_TYPE_MULTISELECT;
    }

    $selectedProjectIds = old('project_ids', $field?->projects?->pluck('id')->toArray() ?? []);
    $selectedProgramIds = old('program_ids', $field?->programs?->pluck('id')->toArray() ?? []);
    $scopeId = $field?->id ? 'logging-field-edit-scope' : 'logging-field-create-scope';
    $savedScope = [
        'mode' => \App\Support\ProjectProgramScope::normalizeMode($field?->program_scope_mode, \App\Models\LoggingField::class)->value,
        'programIds' => array_map('strval', $field?->programs?->modelKeys() ?? []),
    ];
    $optionRows = collect(old('option_rows', []));

    if ($optionRows->isEmpty() && $field) {
        $optionRows = collect($field->normalizedOptions())
            ->map(function ($option, $index) {
                $rowKey = 'option-' . ($option['id'] ?? $index);

                return [
                    'row_key' => $rowKey,
                    'id' => $option['id'] ?? '',
                    'value' => $option['label'] ?? '',
                    '_delete' => '0',
                ];
            });
    }

    $optionRows = $optionRows
        ->map(function ($row, $index) {
            if (!is_array($row)) {
                return null;
            }

            return [
                'row_key' => (string) ($row['row_key'] ?? ('option-row-' . $index)),
                'id' => (string) ($row['id'] ?? ''),
                'value' => (string) ($row['value'] ?? $row['label'] ?? ''),
                '_delete' => !empty($row['_delete']) ? '1' : '0',
            ];
        })
        ->filter()
        ->values()
        ->all();

    $loggingFieldFormConfig = [
        'attachmentGroups' => $attachmentGroups ?? [],
        'savedScope' => $savedScope,
        'fieldName' => $field?->name ?? '',
    ];
@endphp

<x-section-card title="Information">
    <x-form-field label="Name" for="name" name="name" :required="true">
        <input type="text"
               class="form-control @error('name') is-invalid @enderror"
               id="name"
               name="name"
               value="{{ old('name', $field?->name ?? '') }}"
               required>
    </x-form-field>

    <x-form-field label="Type" for="field_type" name="field_type" :required="true">
        <select class="form-select @error('field_type') is-invalid @enderror" id="field_type" name="field_type" required>
            <option value="">Select field type…</option>
            @foreach($fieldTypes as $key => $label)
                <option value="{{ $key }}" {{ $selectedFieldType === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </x-form-field>

    <div class="mb-3 d-none" id="options-field-wrapper">
        <x-inline-string-list
            list-id="logging-field-options"
            name="option_rows"
            :rows="$optionRows"
            label="Options"
            :required="true"
            value-field="label"
            add-button-text="Add Option"
            empty-message="Add at least one option."
            input-placeholder="Enter an option label…"
        />
    </div>

    <x-form-field label="Help Text" for="help_text" name="help_text">
        <textarea class="form-control @error('help_text') is-invalid @enderror" id="help_text" name="help_text" rows="2" maxlength="1000">{{ old('help_text', $field?->help_text ?? '') }}</textarea>
    </x-form-field>

    <x-form-options>
        <x-form-switch
            name="is_active"
            id="is_active"
            label="Active"
            :help="collect($attachmentGroups ?? [])->sum('count') > 0 ? 'Turning this off removes the field from every agreement, activity family and activity type it is on. Answers already logged are kept.' : null"
            :checked="old('is_active', $field?->is_active ?? true)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>

<x-section-card title="Availability">
    <x-project-program-scope-picker
        :scope-id="$scopeId"
        :projects="$projects"
        :selected-project-ids="$selectedProjectIds"
        :selected-program-ids="$selectedProgramIds"
        :show-scope-mode-selector="true"
        :selected-scope-mode="old('program_scope_mode', $field?->program_scope_mode?->value ?? ($field?->exists ? 'all' : 'specific'))"
        :lock-all="$field?->exists && $field?->program_scope_mode?->value === 'all'"
        project-empty-selection-label="All projects"
        program-empty-selection-label="All programs"
    />

    <x-form-field label="Available In" class="mt-4">
        <div class="d-flex flex-wrap gap-3">
            @foreach($availabilityOptions as $key => $label)
                <div class="form-check">
                    <input class="form-check-input"
                           type="checkbox"
                           id="{{ $key }}"
                           name="{{ $key }}"
                           value="1"
                           {{ old($key, $field?->{$key} ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
                    @if(($attachmentGroups[$key]['count'] ?? 0) > 0)
                        @php($usage = $attachmentGroups[$key]['count'].' '.Str::plural($attachmentGroups[$key]['label'], $attachmentGroups[$key]['count']))
                        <div class="small text-muted" data-attachment-used="{{ $key }}">Used on {{ $usage }}</div>
                        <div class="small text-warning-emphasis" data-attachment-removing="{{ $key }}" hidden>
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <span data-removal-text>Will be removed from {{ $usage }}</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-form-field>

    <div aria-live="polite">
        <div class="alert alert-warning small mt-3 mb-0" id="logging-field-detach-warning" hidden>
            <p class="mb-0"><span data-detach-summary></span> Answers already logged on activities are kept and stay editable.</p>
            <details class="app-details mt-2">
                <summary>Show affected records</summary>
                <div class="mt-2">
                    @foreach($attachmentGroups ?? [] as $key => $group)
                        @if($group['count'])
                            <div data-detach-records="{{ $key }}" hidden>
                                @include('logging-fields.partials.attachment-records')
                            </div>
                        @endif
                    @endforeach
                </div>
            </details>
        </div>
    </div>

    <x-form-options class="mt-4">
        <x-form-switch
            name="is_full_width"
            id="is_full_width"
            label="Full width"
            help="Use a full row when this field is shown on an activity form."
            :checked="old('is_full_width', $field?->is_full_width ?? false)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>

<script type="application/json" data-logging-field-form-config>@json($loggingFieldFormConfig)</script>

@push('scripts')
    @vite('resources/js/logging-field-form.js')
@endpush
