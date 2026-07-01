@props([
    'pickerId',
    'name',
    'items' => collect(),
    'options' => null,
    'selectedIds' => [],
    'placeholder' => 'Search...',
    'labelKey' => 'name',
    'valueKey' => 'id',
    'searchKey' => null,
    'emptyMessage' => 'No matches found.',
    'openOnFocus' => true,
    'height' => '300px',
])

@php
    $selectedIds = collect($selectedIds)->map(fn ($id) => (string) $id)->values()->all();

    $searchKey = $searchKey ?: $labelKey;

    $normalizedOptions = collect($options ?? $items)->map(function ($item) use ($labelKey, $valueKey, $searchKey) {
        return [
            'value' => (string) data_get($item, $valueKey),
            'label' => (string) data_get($item, $labelKey),
            'search' => strtolower((string) data_get($item, $searchKey, data_get($item, $labelKey))),
        ];
    })->filter(fn ($option) => $option['value'] !== '')->values()->all();
@endphp

<div id="{{ $pickerId }}"
     class="token-picker position-relative"
     data-token-picker
     data-name="{{ $name }}"
     data-empty-message="{{ $emptyMessage }}"
     data-placeholder="{{ $placeholder }}"
     data-open-on-focus="{{ $openOnFocus ? 'true' : 'false' }}"
     data-selected='@json($selectedIds)'>
    <div class="d-flex flex-wrap gap-1 mb-2" data-token-selected></div>

    <div class="form-control d-flex align-items-center py-1" style="min-height: 42px;">
        <input type="text"
               class="border-0 flex-grow-1"
               style="outline: none; min-width: 160px;"
               data-token-search
               placeholder="{{ $placeholder }}"
               autocomplete="off">
    </div>

    <div class="border rounded p-3 bg-white shadow-sm d-none position-absolute start-0 end-0 mt-1"
        style="z-index: 1060; max-height: {{ $height }}; overflow-y: scroll;"
         data-token-list></div>

    <div data-token-inputs></div>

    <script type="application/json" data-token-options>
        @json($normalizedOptions)
    </script>
</div>

@once
<script>
(function () {
    function parseJson(node, fallback) {
        try {
            return JSON.parse(node.textContent || '');
        } catch (error) {
            return fallback;
        }
    }

    function renderTokenPicker(picker) {
        const searchInput = picker.querySelector('[data-token-search]');
        const selectedWrap = picker.querySelector('[data-token-selected]');
        const listWrap = picker.querySelector('[data-token-list]');
        const hiddenInputs = picker.querySelector('[data-token-inputs]');
        const optionsNode = picker.querySelector('[data-token-options]');

        if (!searchInput || !selectedWrap || !listWrap || !hiddenInputs || !optionsNode) {
            return;
        }

        const name = picker.dataset.name;
        const emptyMessage = picker.dataset.emptyMessage || 'No matches found.';
        const options = parseJson(optionsNode, []);
        const initialSelected = new Set(parseJson({ textContent: picker.dataset.selected || '[]' }, []));
        const selected = new Set(Array.from(initialSelected));
        let allowedValues = null;

        function selectedArray() {
            return Array.from(selected);
        }

        function optionByValue(value) {
            return options.find(opt => String(opt.value) === String(value));
        }

        function normalizedAllowedValues(values) {
            if (!Array.isArray(values)) {
                return null;
            }

            return new Set(values.map(function (value) {
                return String(value);
            }));
        }

        function syncSelectionToAllowedValues() {
            if (allowedValues === null) {
                return;
            }

            Array.from(selected).forEach(function (value) {
                if (!allowedValues.has(String(value))) {
                    selected.delete(String(value));
                }
            });
        }

        function writeHiddenInputs() {
            hiddenInputs.innerHTML = '';
            selectedArray().forEach(function (value) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                hiddenInputs.appendChild(input);
            });
        }

        function renderSelected() {
            selectedWrap.innerHTML = '';

            selectedArray().forEach(function (value) {
                const option = optionByValue(value);
                if (!option) {
                    return;
                }

                const badge = document.createElement('span');
                badge.className = 'badge text-bg-light border d-inline-flex align-items-center gap-1';
                badge.innerHTML = '<span></span><button type="button" class="btn-close" style="font-size: 10px;" aria-label="Remove"></button>';
                badge.querySelector('span').textContent = option.label;
                badge.querySelector('button').addEventListener('click', function () {
                    selected.delete(String(value));
                    renderSelected();
                    renderList();
                    writeHiddenInputs();
                    picker.dispatchEvent(new CustomEvent('token-picker:change', { bubbles: true }));
                });
                selectedWrap.appendChild(badge);
            });
        }

        function filteredOptions(term) {
            const q = (term || '').trim().toLowerCase();
            return options.filter(function (opt) {
                if (allowedValues !== null && !allowedValues.has(String(opt.value))) {
                    return false;
                }
                if (selected.has(String(opt.value))) {
                    return false;
                }
                const haystack = String(opt.search || opt.label).toLowerCase();
                return q === '' || haystack.includes(q);
            });
        }

        function renderList(forceOpen) {
            const list = filteredOptions(searchInput.value);
            listWrap.innerHTML = '';

            if (list.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'text-muted small';
                empty.textContent = emptyMessage;
                listWrap.appendChild(empty);
            } else {
                list.forEach(function (opt) {
                    const row = document.createElement('label');
                    row.className = 'form-check d-flex align-items-start gap-2 mb-2';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'form-check-input mt-1';
                    checkbox.value = String(opt.value);
                    checkbox.checked = selected.has(String(opt.value));
                    checkbox.addEventListener('change', function () {
                        if (checkbox.checked) {
                            selected.add(String(opt.value));
                        } else {
                            selected.delete(String(opt.value));
                        }

                        renderSelected();
                        renderList(true);
                        writeHiddenInputs();
                        picker.dispatchEvent(new CustomEvent('token-picker:change', { bubbles: true }));
                        searchInput.focus();
                    });

                    const text = document.createElement('span');
                    text.className = 'form-check-label';
                    text.textContent = opt.label;

                    row.appendChild(checkbox);
                    row.appendChild(text);
                    listWrap.appendChild(row);
                });
            }

            if (forceOpen) {
                listWrap.classList.remove('d-none');
            }
        }

        function closeList() {
            listWrap.classList.add('d-none');
        }

        picker.addEventListener('token-picker:set', function (event) {
            const values = Array.isArray(event.detail) ? event.detail : [];
            selected.clear();
            values.forEach(function (value) {
                selected.add(String(value));
            });
            syncSelectionToAllowedValues();
            renderSelected();
            if (!listWrap.classList.contains('d-none')) {
                renderList(true);
            }
            writeHiddenInputs();
            picker.dispatchEvent(new CustomEvent('token-picker:change', { bubbles: true }));
        });

        picker.addEventListener('token-picker:restrict', function (event) {
            allowedValues = normalizedAllowedValues(event.detail);
            syncSelectionToAllowedValues();
            renderSelected();
            if (!listWrap.classList.contains('d-none')) {
                renderList(true);
            }
            writeHiddenInputs();
            picker.dispatchEvent(new CustomEvent('token-picker:change', { bubbles: true }));
        });

        searchInput.addEventListener('focus', function () {
            if (picker.dataset.openOnFocus === 'true') {
                renderList(true);
            }
        });
        searchInput.addEventListener('click', function () {
            renderList(true);
        });
        searchInput.addEventListener('input', function () {
            renderList(true);
        });
        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
            if (event.key === 'Escape') {
                closeList();
            }
        });

        document.addEventListener('click', function (event) {
            if (!picker.contains(event.target)) {
                closeList();
            }
        });

        renderSelected();
        renderList(false);
        closeList();
        writeHiddenInputs();

        // Dispatch initialization event when picker is ready with initial values
        if (initialSelected.size > 0) {
            setTimeout(function() {
                picker.dispatchEvent(new CustomEvent('token-picker:initialized', { bubbles: true }));
            }, 0);
        }
    }

    function initTokenPickers(root) {
        (root || document).querySelectorAll('[data-token-picker]').forEach(function (picker) {
            if (picker.dataset.tokenPickerInitialized === 'true') {
                return;
            }
            picker.dataset.tokenPickerInitialized = 'true';
            renderTokenPicker(picker);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTokenPickers(document);
    });

    document.body.addEventListener('htmx:afterSwap', function (event) {
        initTokenPickers(event.target);
    });
})();
</script>
@endonce
