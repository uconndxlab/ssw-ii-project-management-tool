@props([
    'listId',
    'name',
    'fields' => [],
    'rows' => [],
    'maxRows' => null,
    'sortable' => false,
    'primaryField' => null,
    'primaryLabel' => 'Primary',
    'addButtonText' => 'Add Item',
    'emptyMessage' => 'None added yet.',
])

@php
    $fields = collect($fields)
        ->map(fn ($field) => array_merge([
            'key' => null,
            'label' => null,
            'type' => 'text',
            'placeholder' => '',
            'col' => 'col-md-3',
            'maxlength' => null,
        ], $field))
        ->filter(fn ($field) => filled($field['key']))
        ->values();

    $normalizedRows = collect($rows)
        ->filter(fn ($row) => is_array($row))
        ->map(function ($row, $index) use ($fields, $primaryField) {
            $rowKey = (string) ($row['row_key'] ?? ('row-' . $index));
            $values = [];

            foreach ($fields as $field) {
                $values[$field['key']] = (string) ($row[$field['key']] ?? '');
            }

            return [
                'row_key' => $rowKey,
                'id' => (string) ($row['id'] ?? ''),
                '_delete' => !empty($row['_delete']) ? '1' : '0',
                'is_primary' => ($primaryField && !empty($row[$primaryField])) ? '1' : '0',
                'values' => $values,
            ];
        })
        ->values();

    $visibleRows = $normalizedRows->where('_delete', '0');
    $atMax = $maxRows && $visibleRows->count() >= $maxRows;

    $rowFieldError = fn ($rowKey, $fieldKey) => $errors->first("{$name}.{$rowKey}.{$fieldKey}");

    $generalErrors = collect($errors->get($name))->flatten()->unique()->values();
@endphp

<div id="{{ $listId }}"
     class="inline-record-list"
     data-inline-record-list
     data-name="{{ $name }}"
     data-fields='@json($fields->values())'
     data-max-rows="{{ $maxRows ?? '' }}"
     data-sortable="{{ $sortable ? 'true' : 'false' }}"
     data-primary-field="{{ $primaryField }}">

    <div data-inline-record-rows>
        @foreach($normalizedRows as $row)
            <div class="inline-record-row card mb-2 {{ $row['_delete'] === '1' ? 'd-none' : '' }} {{ $row['is_primary'] === '1' ? 'border-primary bg-primary-subtle' : '' }}"
                 data-inline-record-row="{{ $row['row_key'] }}">
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-start gap-2">
                        @if($sortable)
                            <span class="text-muted inline-record-drag-handle mt-2" data-drag-handle aria-hidden="true">
                                <i class="bi bi-grip-vertical"></i>
                            </span>
                        @endif

                        @if($row['id'] !== '')
                            <input type="hidden" name="{{ $name }}[{{ $row['row_key'] }}][id]" value="{{ $row['id'] }}">
                        @endif
                        <input type="hidden" name="{{ $name }}[{{ $row['row_key'] }}][_delete]" value="{{ $row['_delete'] }}" data-inline-record-delete>
                        @if($primaryField)
                            <input type="hidden" name="{{ $name }}[{{ $row['row_key'] }}][{{ $primaryField }}]" value="{{ $row['is_primary'] }}" data-inline-record-primary-input>
                        @endif

                        <div class="row g-2 flex-grow-1">
                            @foreach($fields as $field)
                                @php $fieldError = $rowFieldError($row['row_key'], $field['key']); @endphp
                                <div class="{{ $field['col'] }}">
                                    @if($field['label'])
                                        <label class="form-label small text-muted mb-1">{{ $field['label'] }}</label>
                                    @endif
                                    <input type="{{ $field['type'] }}"
                                           class="form-control form-control-sm @if($fieldError) is-invalid @endif"
                                           name="{{ $name }}[{{ $row['row_key'] }}][{{ $field['key'] }}]"
                                           value="{{ $row['values'][$field['key']] }}"
                                           placeholder="{{ $field['placeholder'] }}"
                                           @if($field['maxlength']) maxlength="{{ $field['maxlength'] }}" @endif
                                           data-inline-record-input="{{ $field['key'] }}">
                                    @if($fieldError)
                                        <div class="invalid-feedback">{{ $fieldError }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="d-flex flex-column align-items-center gap-1 mt-1">
                            @if($primaryField)
                                <button type="button"
                                        class="btn btn-link p-0 inline-record-primary-toggle {{ $row['is_primary'] === '1' ? 'text-primary' : 'text-muted' }}"
                                        data-inline-record-primary-toggle
                                        title="Mark as {{ $primaryLabel }}"
                                        aria-label="Mark as {{ $primaryLabel }}">
                                    <i class="bi {{ $row['is_primary'] === '1' ? 'bi-star-fill' : 'bi-star' }}"></i>
                                </button>
                            @endif
                            <button type="button" class="btn btn-link p-0 text-danger" data-inline-record-remove aria-label="Remove">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="text-muted small mt-1 {{ $visibleRows->isNotEmpty() ? 'd-none' : '' }}" data-inline-record-empty>
        {{ $emptyMessage }}
    </div>

    <div class="mt-2">
        <button type="button" class="btn btn-outline-primary" data-inline-record-add @if($atMax) disabled @endif>
            + {{ $addButtonText }}
        </button>
        @if($maxRows)
            <div class="form-text small {{ $atMax ? '' : 'd-none' }}" data-inline-record-max-hint>
                Maximum of {{ $maxRows }} reached.
            </div>
        @endif
    </div>

    @if($generalErrors->isNotEmpty())
        @foreach($generalErrors as $message)
            <div class="text-danger small mt-1">{{ $message }}</div>
        @endforeach
    @endif
</div>
