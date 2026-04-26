@props([
    'pickerId',
    'name',
    'items' => collect(),
    'selectedIds' => [],
    'placeholder' => 'Search...',
    'labelKey' => 'name',
    'valueKey' => 'id',
    'emptyMessage' => 'No matches found.',
])

@php
    $selectedIds = collect($selectedIds)->map(fn ($id) => (string) $id)->values()->all();

    $options = collect($items)->map(function ($item) use ($labelKey, $valueKey) {
        return [
            'value' => (string) data_get($item, $valueKey),
            'label' => (string) data_get($item, $labelKey),
        ];
    })->filter(fn ($option) => $option['value'] !== '')->values()->all();
@endphp

<div id="{{ $pickerId }}"
     class="token-picker position-relative"
     data-token-picker
     data-name="{{ $name }}"
     data-empty-message="{{ $emptyMessage }}"
     data-placeholder="{{ $placeholder }}"
     data-selected='@json($selectedIds)'>
    <div class="form-control d-flex flex-wrap gap-1 align-items-center py-1" style="min-height: 42px;">
        <div class="d-flex flex-wrap gap-1" data-token-selected></div>
        <input type="text"
               class="border-0 flex-grow-1"
               style="outline: none; min-width: 160px;"
               data-token-search
               placeholder="{{ $placeholder }}"
               autocomplete="off">
    </div>

    <div class="list-group position-absolute start-0 end-0 mt-1 shadow-sm d-none"
         style="z-index: 1060; max-height: 220px; overflow-y: auto;"
         data-token-dropdown></div>

    <div data-token-inputs></div>

    <script type="application/json" data-token-options>
        @json($options)
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
        const dropdown = picker.querySelector('[data-token-dropdown]');
        const hiddenInputs = picker.querySelector('[data-token-inputs]');
        const optionsNode = picker.querySelector('[data-token-options]');

        if (!searchInput || !selectedWrap || !dropdown || !hiddenInputs || !optionsNode) {
            return;
        }

        const name = picker.dataset.name;
        const emptyMessage = picker.dataset.emptyMessage || 'No matches found.';
        const options = parseJson(optionsNode, []);
        const initialSelected = new Set(parseJson({ textContent: picker.dataset.selected || '[]' }, []));
        const selected = new Set(Array.from(initialSelected));

        function selectedArray() {
            return Array.from(selected);
        }

        function optionByValue(value) {
            return options.find(opt => String(opt.value) === String(value));
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
                    renderDropdown();
                    writeHiddenInputs();
                    picker.dispatchEvent(new CustomEvent('token-picker:change', { bubbles: true }));
                });
                selectedWrap.appendChild(badge);
            });
        }

        function filteredOptions(term) {
            const q = (term || '').trim().toLowerCase();
            return options.filter(function (opt) {
                if (selected.has(String(opt.value))) {
                    return false;
                }
                return q === '' || String(opt.label).toLowerCase().includes(q);
            });
        }

        function renderDropdown() {
            const list = filteredOptions(searchInput.value);
            dropdown.innerHTML = '';

            if (list.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'list-group-item text-muted small';
                empty.textContent = emptyMessage;
                dropdown.appendChild(empty);
            } else {
                list.slice(0, 25).forEach(function (opt, index) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action';
                    btn.textContent = opt.label;
                    btn.dataset.value = String(opt.value);
                    if (index === 0) {
                        btn.dataset.firstMatch = 'true';
                    }
                    btn.addEventListener('click', function () {
                        selected.add(String(opt.value));
                        searchInput.value = '';
                        renderSelected();
                        renderDropdown();
                        writeHiddenInputs();
                        picker.dispatchEvent(new CustomEvent('token-picker:change', { bubbles: true }));
                        searchInput.focus();
                    });
                    dropdown.appendChild(btn);
                });
            }

            dropdown.classList.remove('d-none');
        }

        picker.addEventListener('token-picker:set', function (event) {
            const values = Array.isArray(event.detail) ? event.detail : [];
            selected.clear();
            values.forEach(function (value) {
                selected.add(String(value));
            });
            renderSelected();
            renderDropdown();
            writeHiddenInputs();
            picker.dispatchEvent(new CustomEvent('token-picker:change', { bubbles: true }));
        });

        searchInput.addEventListener('focus', renderDropdown);
        searchInput.addEventListener('input', renderDropdown);
        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                const first = dropdown.querySelector('[data-first-match="true"]');
                if (first) {
                    event.preventDefault();
                    first.click();
                }
            }
            if (event.key === 'Escape') {
                dropdown.classList.add('d-none');
            }
        });

        document.addEventListener('click', function (event) {
            if (!picker.contains(event.target)) {
                dropdown.classList.add('d-none');
            }
        });

        renderSelected();
        writeHiddenInputs();
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
