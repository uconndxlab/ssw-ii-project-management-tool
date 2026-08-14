@props([
    'listId',
    'name',
    'rows' => [],
    'label' => null,
    'required' => false,
    'addButtonText' => 'Add Item',
    'emptyMessage' => 'None added yet.',
    'suggestions' => [],
    'inputPlaceholder' => 'Enter a value...',
    'valueField' => 'value',
])

@php
    $normalizedRows = collect($rows)
        ->filter(fn ($row) => is_array($row))
        ->map(function ($row, $index) {
            $rowKey = (string) ($row['row_key'] ?? ('row-' . $index));

            return [
                'row_key' => $rowKey,
                'id' => (string) ($row['id'] ?? ''),
                'value' => (string) ($row['value'] ?? $row['label'] ?? ''),
                '_delete' => !empty($row['_delete']) ? '1' : '0',
            ];
        })
        ->values();

    $suggestions = collect($suggestions)
        ->filter(fn ($value) => filled($value))
        ->map(fn ($value) => (string) $value)
        ->unique()
        ->values();

    $errorMessages = collect($errors->get($name))
        ->flatten()
        ->merge(collect($errors->get($name . '.*.value'))->flatten())
        ->merge(collect($errors->get($name . '.*.label'))->flatten())
        ->unique()
        ->values();

    $datalistId = $listId . '-suggestions';
    $visibleRows = $normalizedRows->where('_delete', '0');
@endphp

<div id="{{ $listId }}"
     class="inline-string-list"
     data-inline-string-list
     data-name="{{ $name }}"
     data-value-field="{{ $valueField }}"
     data-input-placeholder="{{ $inputPlaceholder }}"
     data-empty-message="{{ $emptyMessage }}"
     data-datalist-id="{{ $suggestions->isNotEmpty() ? $datalistId : '' }}">
    @if($label)
        <label class="form-label{{ $required ? ' required-label' : '' }}">{{ $label }}</label>
    @endif

    <div class="d-grid gap-2" data-inline-string-rows style="max-width: 32rem;">
        @foreach($normalizedRows as $row)
            <div class="{{ $row['_delete'] === '1' ? 'd-none' : '' }}" data-inline-string-row="{{ $row['row_key'] }}">
                @if($row['id'] !== '')
                    <input type="hidden" name="{{ $name }}[{{ $row['row_key'] }}][id]" value="{{ $row['id'] }}">
                @endif
                <input type="hidden" name="{{ $name }}[{{ $row['row_key'] }}][_delete]" value="{{ $row['_delete'] }}" data-inline-string-delete>
                <div class="d-flex align-items-center gap-2">
                    <input type="text"
                           class="form-control @if($errorMessages->isNotEmpty()) is-invalid @endif"
                           name="{{ $name }}[{{ $row['row_key'] }}][{{ $valueField }}]"
                           value="{{ $row['value'] }}"
                           placeholder="{{ $inputPlaceholder }}"
                           @if($suggestions->isNotEmpty()) list="{{ $datalistId }}" @endif
                           data-inline-string-input>
                    <button type="button"
                            class="btn btn-outline-danger flex-shrink-0"
                            aria-label="Remove"
                            data-inline-string-remove>
                        -
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    <div class="text-muted small mt-2 {{ $visibleRows->isNotEmpty() ? 'd-none' : '' }}" data-inline-string-empty>
        {{ $emptyMessage }}
    </div>

    <div class="mt-2" style="max-width: 32rem;">
        <button type="button" class="btn btn-outline-primary w-100" data-inline-string-add>
            + {{ $addButtonText }}
        </button>
    </div>

    @if($errorMessages->isNotEmpty())
        @foreach($errorMessages as $message)
            <div class="text-danger small mt-1">{{ $message }}</div>
        @endforeach
    @endif

    @if($suggestions->isNotEmpty())
        <datalist id="{{ $datalistId }}">
            @foreach($suggestions as $suggestion)
                <option value="{{ $suggestion }}"></option>
            @endforeach
        </datalist>
    @endif
</div>

@once
<script>
(function () {
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initializeInlineStringList(list) {
        if (list.dataset.inlineStringListReady === 'true') {
            return;
        }

        const rowsContainer = list.querySelector('[data-inline-string-rows]');
        const addButton = list.querySelector('[data-inline-string-add]');
        const emptyState = list.querySelector('[data-inline-string-empty]');
        const name = list.dataset.name;
        const valueField = list.dataset.valueField || 'value';
        const inputPlaceholder = list.dataset.inputPlaceholder || 'Enter a value...';
        const datalistId = list.dataset.datalistId || '';

        if (!rowsContainer || !addButton || !name) {
            return;
        }

        let nextIndex = rowsContainer.querySelectorAll('[data-inline-string-row]').length;

        function visibleRows() {
            return Array.from(rowsContainer.querySelectorAll('[data-inline-string-row]')).filter(function (row) {
                return !row.classList.contains('d-none');
            });
        }

        function syncEmptyState() {
            emptyState?.classList.toggle('d-none', visibleRows().length > 0);
        }

        function rowMarkup(rowKey) {
            const listAttr = datalistId ? ` list="${escapeHtml(datalistId)}"` : '';

            return `
                <div data-inline-string-row="${escapeHtml(rowKey)}">
                    <input type="hidden" name="${escapeHtml(name)}[${escapeHtml(rowKey)}][_delete]" value="0" data-inline-string-delete>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text"
                               class="form-control"
                               name="${escapeHtml(name)}[${escapeHtml(rowKey)}][${escapeHtml(valueField)}]"
                               value=""
                               placeholder="${escapeHtml(inputPlaceholder)}"
                               ${listAttr}
                               data-inline-string-input>
                        <button type="button" class="btn btn-outline-danger flex-shrink-0" aria-label="Remove" data-inline-string-remove>-</button>
                    </div>
                </div>
            `;
        }

        function addRow() {
            const rowKey = 'row-new-' + Date.now() + '-' + (nextIndex++);
            rowsContainer.insertAdjacentHTML('beforeend', rowMarkup(rowKey));
            const input = rowsContainer.querySelector('[data-inline-string-row="' + CSS.escape(rowKey) + '"] [data-inline-string-input]');
            input?.focus();
            syncEmptyState();

            return input;
        }

        addButton.addEventListener('click', function () {
            addRow();
        });

        rowsContainer.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            const input = event.target.closest('[data-inline-string-input]');

            if (!input) {
                return;
            }

            event.preventDefault();

            const allInputs = visibleRows().map(function (row) {
                return row.querySelector('[data-inline-string-input]');
            }).filter(Boolean);
            const nextInput = allInputs[allInputs.indexOf(input) + 1];

            if (nextInput) {
                nextInput.focus();
                nextInput.select();

                return;
            }

            addRow();
        });

        rowsContainer.addEventListener('click', function (event) {
            const button = event.target.closest('[data-inline-string-remove]');

            if (!button) {
                return;
            }

            const row = button.closest('[data-inline-string-row]');

            if (!row) {
                return;
            }

            const idInput = row.querySelector('input[name$="[id]"]');
            const deleteInput = row.querySelector('[data-inline-string-delete]');

            if (idInput && idInput.value) {
                if (deleteInput) {
                    deleteInput.value = '1';
                }
                row.classList.add('d-none');
                row.querySelectorAll('input, textarea, select').forEach(function (field) {
                    if (field !== deleteInput && field !== idInput) {
                        field.disabled = true;
                    }
                });
            } else {
                row.remove();
            }

            syncEmptyState();
        });

        list.dataset.inlineStringListReady = 'true';
        syncEmptyState();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-inline-string-list]').forEach(initializeInlineStringList);
    });
})();
</script>
@endonce
