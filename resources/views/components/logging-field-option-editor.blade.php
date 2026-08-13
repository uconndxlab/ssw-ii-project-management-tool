@props([
    'listId',
    'name',
    'rows' => [],
    'label' => null,
    'addButtonText' => 'Add Option',
    'inputPlaceholder' => 'Enter a label...',
])

@php
    $normalizedRows = collect($rows)
        ->filter(fn ($row) => is_array($row))
        ->map(function ($row, $index) {
            return [
                'row_key' => (string) ($row['row_key'] ?? ('option-row-' . $index)),
                'id' => (string) ($row['id'] ?? ''),
                'label' => (string) ($row['value'] ?? $row['label'] ?? ''),
            ];
        })
        ->values();

    $errorMessages = collect($errors->get($name))
        ->flatten()
        ->merge(collect($errors->get($name . '.*.label'))->flatten())
        ->unique()
        ->values();
@endphp

<div id="{{ $listId }}"
     data-logging-field-option-editor
     data-name="{{ $name }}"
     data-input-placeholder="{{ $inputPlaceholder }}">
    @if($label)
        <label class="form-label">{{ $label }} <span class="text-danger">*</span></label>
    @endif

    <div class="row g-2" data-option-editor-rows>
        @foreach($normalizedRows as $row)
            <div class="col-12" data-option-editor-row data-row-key="{{ $row['row_key'] }}">
                <div class="col-12 col-md-6 px-0">
                    <div class="d-flex align-items-center gap-2">
                        <input type="hidden" name="{{ $name }}[{{ $row['row_key'] }}][id]" value="{{ $row['id'] }}" data-option-editor-id>
                        <input type="text"
                               class="form-control @if($errorMessages->isNotEmpty()) is-invalid @endif"
                               name="{{ $name }}[{{ $row['row_key'] }}][label]"
                               value="{{ $row['label'] }}"
                               placeholder="{{ $inputPlaceholder }}"
                               data-option-editor-input>
                        <button type="button"
                                class="btn btn-outline-danger flex-shrink-0"
                                aria-label="Remove option"
                                data-bs-toggle="tooltip"
                                data-bs-title="Remove option"
                                data-option-editor-remove>
                            -
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-2 mt-2">
        <div class="col-12 col-md-6">
            <button type="button" class="btn btn-outline-primary w-100" data-option-editor-add>
                + {{ $addButtonText }}
            </button>
        </div>
    </div>

    @if($errorMessages->isNotEmpty())
        @foreach($errorMessages as $message)
            <div class="text-danger small mt-1">{{ $message }}</div>
        @endforeach
    @endif
</div>

@once
<script>
(function () {
    function initializeOptionEditor(editor) {
        if (!editor || editor.dataset.optionEditorReady === 'true') {
            return;
        }

        const rowsContainer = editor.querySelector('[data-option-editor-rows]');
        const addButton = editor.querySelector('[data-option-editor-add]');
        const fieldName = editor.dataset.name;
        const inputPlaceholder = editor.dataset.inputPlaceholder || 'Enter a label...';

        if (!rowsContainer || !addButton || !fieldName) {
            return;
        }

        let nextIndex = rowsContainer.querySelectorAll('[data-option-editor-row]').length;

        function rowMarkup(rowKey, idValue, labelValue) {
            const safeId = String(idValue || '')
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            const safeLabel = String(labelValue || '')
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            const safePlaceholder = String(inputPlaceholder)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            return `
                <div class="col-12" data-option-editor-row data-row-key="${rowKey}">
                    <div class="col-12 col-md-6 px-0">
                        <div class="d-flex align-items-center gap-2">
                            <input type="hidden" name="${fieldName}[${rowKey}][id]" value="${safeId}" data-option-editor-id>
                            <input type="text" class="form-control" name="${fieldName}[${rowKey}][label]" value="${safeLabel}" placeholder="${safePlaceholder}" data-option-editor-input>
                            <button type="button" class="btn btn-outline-danger flex-shrink-0" aria-label="Remove option" data-bs-toggle="tooltip" data-bs-title="Remove option" data-option-editor-remove>
                                -
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        function inputs() {
            return Array.from(rowsContainer.querySelectorAll('[data-option-editor-input]'));
        }

        function addRow(value) {
            const rowKey = 'option-row-' + nextIndex;
            nextIndex += 1;
            rowsContainer.insertAdjacentHTML('beforeend', rowMarkup(rowKey, '', value || ''));
            const input = rowsContainer.querySelector('[data-row-key="' + rowKey + '"] [data-option-editor-input]');
            input?.focus();

            return input;
        }

        function ensureRow() {
            if (inputs().length === 0) {
                addRow('');
            }
        }

        addButton.addEventListener('click', function () {
            addRow('');
        });

        rowsContainer.addEventListener('click', function (event) {
            const button = event.target.closest('[data-option-editor-remove]');

            if (!button) {
                return;
            }

            const row = button.closest('[data-option-editor-row]');
            row?.remove();
            ensureRow();
            editor.dispatchEvent(new CustomEvent('logging-field-option-editor:change', { bubbles: true }));
        });

        rowsContainer.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            const input = event.target.closest('[data-option-editor-input]');

            if (!input) {
                return;
            }

            event.preventDefault();

            const allInputs = inputs();
            const index = allInputs.indexOf(input);
            const nextInput = allInputs[index + 1];

            if (nextInput) {
                nextInput.focus();
                nextInput.select();

                return;
            }

            addRow('');
        });

        rowsContainer.addEventListener('input', function () {
            editor.dispatchEvent(new CustomEvent('logging-field-option-editor:change', { bubbles: true }));
        });

        ensureRow();
        editor.dataset.optionEditorReady = 'true';
    }

    function initializeAll(root) {
        root.querySelectorAll('[data-logging-field-option-editor]').forEach(initializeOptionEditor);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initializeAll(document);
        });
    } else {
        initializeAll(document);
    }
})();
</script>
@endonce
