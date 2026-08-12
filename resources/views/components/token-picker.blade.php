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
    'showSelected' => true,
    'height' => '300px',
    'disabled' => false,
    'disabledPlaceholder' => null,
    'emptySelectionLabel' => null,
    'entity' => null,
    'entityKind' => null,
])

@php
    use App\Support\EntityBadge;

    $selectedIds = collect($selectedIds)->map(fn ($id) => (string) $id)->values()->all();

    $searchKey = $searchKey ?: $labelKey;

    $resolvedEntity = filled($entity) ? $entity : $entityKind;

    $entityBadgeClass = filled($resolvedEntity)
        ? EntityBadge::relationClasses($resolvedEntity)
        : null;

    $normalizedOptions = collect($options ?? $items)->map(function ($item) use ($labelKey, $valueKey, $searchKey, $entityBadgeClass, $resolvedEntity) {
        $optionEntity = data_get($item, 'entity', $resolvedEntity);
        $optionBadgeClass = filled($optionEntity)
            ? EntityBadge::relationClasses($optionEntity)
            : null;

        return [
            'value' => (string) data_get($item, $valueKey),
            'label' => (string) data_get($item, $labelKey),
            'search' => strtolower((string) data_get($item, $searchKey, data_get($item, $labelKey))),
            'entity' => $optionEntity,
            'context' => data_get($item, 'context'),
            'contextLabels' => array_values(array_filter(
                data_get($item, 'contextLabels') ?? (data_get($item, 'context') ? [data_get($item, 'context')] : []),
                fn ($label) => $label !== null && $label !== ''
            )),
            'contextBadgeClass' => data_get($item, 'contextBadgeClass', $optionBadgeClass ?? $entityBadgeClass ?? 'bg-primary-subtle text-primary-emphasis border'),
            'meta' => filled(data_get($item, 'meta'))
                ? (string) data_get($item, 'meta')
                : (filled(data_get($item, 'po_number')) ? (string) data_get($item, 'po_number') : null),
            'selectedBadgeClass' => data_get($item, 'selectedBadgeClass', $optionBadgeClass ?? $entityBadgeClass),
        ];
    })->filter(fn ($option) => $option['value'] !== '')->values()->all();
@endphp

<div id="{{ $pickerId }}"
     class="token-picker position-relative"
     data-token-picker
     data-name="{{ $name }}"
     data-empty-message="{{ $emptyMessage }}"
     data-placeholder="{{ $placeholder }}"
     data-disabled-placeholder="{{ $disabledPlaceholder ?? $placeholder }}"
     data-open-on-focus="{{ $openOnFocus ? 'true' : 'false' }}"
     data-disabled="{{ $disabled ? 'true' : 'false' }}"
     data-show-selected="{{ $showSelected ? 'true' : 'false' }}"
     data-empty-selection-label="{{ $emptySelectionLabel ?? '' }}"
     data-selected-badge-class="{{ $entityBadgeClass ?? '' }}"
     data-selected='@json($selectedIds)'>
    <div class="d-flex flex-wrap gap-1 mb-2 {{ $showSelected ? '' : 'd-none' }}" data-token-selected></div>

    <div class="form-control d-flex align-items-center py-1" style="min-height: 42px;" data-token-control>
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
<style>
    .token-picker-control-disabled {
        background-color: var(--bs-secondary-bg);
        opacity: 1;
        cursor: not-allowed;
    }

    .token-picker-control-disabled [data-token-search] {
        background-color: transparent;
        cursor: not-allowed;
    }
</style>
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
        const controlWrap = picker.querySelector('[data-token-control]');

        if (!searchInput || !selectedWrap || !listWrap || !hiddenInputs || !optionsNode || !controlWrap) {
            return;
        }

        const name = picker.dataset.name;
        const emptyMessage = picker.dataset.emptyMessage || 'No matches found.';
        const defaultPlaceholder = picker.dataset.placeholder || 'Search...';
        let disabledPlaceholder = picker.dataset.disabledPlaceholder || defaultPlaceholder;
        const showSelected = picker.dataset.showSelected === 'true';
        const emptySelectionLabel = (picker.dataset.emptySelectionLabel || '').trim();
        const defaultSelectedBadgeClass = (picker.dataset.selectedBadgeClass || '').trim();
        const options = parseJson(optionsNode, []);
        const initialSelected = new Set(parseJson({ textContent: picker.dataset.selected || '[]' }, []));
        const selected = new Set(Array.from(initialSelected));
        let allowedValues = null;
        let isDisabled = picker.dataset.disabled === 'true';

        function selectedArray() {
            return Array.from(selected);
        }

        function optionByValue(value) {
            return options.find(opt => String(opt.value) === String(value));
        }

        function appendOptionPrimaryLabel(container, opt) {
            const nameSpan = document.createElement('span');
            nameSpan.textContent = opt.label;
            if (opt.meta) {
                nameSpan.className = 'me-1';
            }
            container.appendChild(nameSpan);

            if (opt.meta) {
                const metaSpan = document.createElement('span');
                metaSpan.className = 'small text-muted opacity-75';
                metaSpan.textContent = '| ' + String(opt.meta);
                container.appendChild(metaSpan);
            }
        }

        function selectedBadgeClasses(opt) {
            if (opt.selectedBadgeClass) {
                return String(opt.selectedBadgeClass);
            }

            if (defaultSelectedBadgeClass !== '') {
                return defaultSelectedBadgeClass;
            }

            return 'text-bg-light border';
        }

        function normalizedAllowedValues(values) {
            if (!Array.isArray(values)) {
                return null;
            }

            return new Set(values.map(function (value) {
                return String(value);
            }));
        }

        function upsertOption(rawOption) {
            if (typeof rawOption !== 'object' || rawOption === null) {
                return null;
            }

            const value = String(rawOption.value || '').trim();

            if (value === '') {
                return null;
            }

            const existing = optionByValue(value);
            const normalized = {
                value: value,
                label: String(rawOption.label || value),
                search: String(rawOption.search || rawOption.label || value).toLowerCase(),
                entity: rawOption.entity || null,
                context: rawOption.context || null,
                contextLabels: Array.isArray(rawOption.contextLabels)
                    ? rawOption.contextLabels.filter(function (label) {
                        return label !== null && String(label).trim() !== '';
                    }).map(function (label) {
                        return String(label);
                    })
                    : [],
                contextBadgeClass: rawOption.contextBadgeClass || null,
                meta: rawOption.meta || null,
                selectedBadgeClass: rawOption.selectedBadgeClass || null,
            };

            if (existing) {
                Object.assign(existing, normalized);
                return existing;
            }

            options.push(normalized);
            options.sort(function (left, right) {
                return String(left.label).localeCompare(String(right.label), undefined, { sensitivity: 'base' });
            });

            return normalized;
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
            if (!showSelected) {
                selectedWrap.innerHTML = '';
                return;
            }

            selectedWrap.innerHTML = '';

            if (selected.size === 0 && emptySelectionLabel !== '') {
                const allBadge = document.createElement('span');
                allBadge.className = 'badge ' + (defaultSelectedBadgeClass !== '' ? defaultSelectedBadgeClass : 'bg-primary-subtle text-primary-emphasis border') + ' d-inline-flex align-items-center';
                allBadge.textContent = emptySelectionLabel;
                selectedWrap.appendChild(allBadge);
            }

            selectedArray().forEach(function (value) {
                const option = optionByValue(value);
                if (!option) {
                    return;
                }

                const badge = document.createElement('span');
                badge.className = 'badge ' + selectedBadgeClasses(option) + ' d-inline-flex align-items-center gap-1';
                badge.innerHTML = '<span class="d-inline-flex flex-wrap align-items-baseline gap-0"></span><button type="button" class="btn-close" style="font-size: 10px;" aria-label="Remove"></button>';
                appendOptionPrimaryLabel(badge.querySelector('span'), option);
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

        function appendContextBadges(textWrap, opt) {
            const badgeClass = opt.contextBadgeClass || 'bg-primary-subtle text-primary-emphasis border';
            const labels = Array.isArray(opt.contextLabels) && opt.contextLabels.length > 0
                ? opt.contextLabels
                : (opt.context ? [String(opt.context)] : []);

            labels.forEach(function (label) {
                const badge = document.createElement('span');
                badge.className = 'badge ' + badgeClass;
                badge.textContent = String(label);
                textWrap.appendChild(badge);
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
            if (isDisabled) {
                closeList();
                return;
            }

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

                    const textWrap = document.createElement('div');
                    textWrap.className = 'form-check-label d-flex flex-wrap align-items-center gap-2';

                    appendOptionPrimaryLabel(textWrap, opt);

                    appendContextBadges(textWrap, opt);

                    row.appendChild(checkbox);
                    row.appendChild(textWrap);
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

        function applyDisabledState() {
            searchInput.disabled = isDisabled;
            searchInput.placeholder = isDisabled ? disabledPlaceholder : defaultPlaceholder;
            controlWrap.classList.toggle('token-picker-control-disabled', isDisabled);
            controlWrap.classList.toggle('text-muted', isDisabled);

            if (isDisabled) {
                searchInput.value = '';
                closeList();
            }
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

        picker.addEventListener('token-picker:update-option-contexts', function (event) {
            const contexts = typeof event.detail === 'object' && event.detail !== null ? event.detail : {};

            options.forEach(function (opt) {
                if (Object.prototype.hasOwnProperty.call(contexts, String(opt.value))) {
                    const context = contexts[String(opt.value)];

                    if (Array.isArray(context)) {
                        opt.contextLabels = context.filter(function (label) {
                            return label !== null && String(label).trim() !== '';
                        });
                        opt.context = null;
                    } else {
                        opt.context = context && String(context).trim() !== '' ? String(context) : null;
                        opt.contextLabels = opt.context ? [opt.context] : [];
                    }
                }
            });

            if (!listWrap.classList.contains('d-none')) {
                renderList(true);
            }
        });

        picker.addEventListener('token-picker:add-option', function (event) {
            const detail = typeof event.detail === 'object' && event.detail !== null ? event.detail : {};
            const option = upsertOption(detail.option || detail);

            if (!option) {
                return;
            }

            if (detail.select !== false) {
                selected.add(String(option.value));
                renderSelected();
                writeHiddenInputs();
                picker.dispatchEvent(new CustomEvent('token-picker:change', { bubbles: true }));
            }

            if (!listWrap.classList.contains('d-none')) {
                renderList(true);
            }
        });

        picker.addEventListener('token-picker:set-disabled', function (event) {
            const detail = event.detail;

            if (typeof detail === 'object' && detail !== null) {
                isDisabled = !!detail.disabled;

                if (typeof detail.placeholder === 'string' && detail.placeholder.trim() !== '') {
                    disabledPlaceholder = detail.placeholder;
                }
            } else {
                isDisabled = !!detail;
            }

            applyDisabledState();
        });

        searchInput.addEventListener('focus', function () {
            if (isDisabled) {
                return;
            }

            if (picker.dataset.openOnFocus === 'true') {
                renderList(true);
            }
        });
        searchInput.addEventListener('click', function () {
            if (isDisabled) {
                return;
            }

            renderList(true);
        });
        searchInput.addEventListener('input', function () {
            if (isDisabled) {
                return;
            }

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
        applyDisabledState();
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
