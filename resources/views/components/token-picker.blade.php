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
    'labelledBy' => null,
    'ariaLabel' => null,
    'single' => false,
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
            'contextBadgeClass' => data_get($item, 'contextBadgeClass', $optionBadgeClass ?? $entityBadgeClass ?? 'app-chip'),
            'meta' => filled(data_get($item, 'meta'))
                ? (string) data_get($item, 'meta')
                : (filled(data_get($item, 'po_number')) ? (string) data_get($item, 'po_number') : null),
            'selectedBadgeClass' => data_get($item, 'selectedBadgeClass', $optionBadgeClass ?? $entityBadgeClass),
        ];
    })->filter(fn ($option) => $option['value'] !== '')->values()->all();

    $errorKey = \Illuminate\Support\Str::before($name, '[');
    $hasError = collect($errors->keys())->contains(
        fn ($key) => $key === $errorKey || str_starts_with((string) $key, $errorKey . '.')
    );
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
     data-single="{{ $single ? 'true' : 'false' }}"
     data-empty-selection-label="{{ $emptySelectionLabel ?? '' }}"
     data-selected-badge-class="{{ $entityBadgeClass ?? '' }}"
     data-selected='@json($selectedIds)'>
    <div class="d-flex flex-wrap gap-1 mb-2 {{ $showSelected ? '' : 'd-none' }}" data-token-selected></div>

    <div class="form-control d-flex align-items-center py-1{{ $hasError ? ' is-invalid' : '' }}" data-token-control>
        <input type="text"
               class="border-0 flex-grow-1"
               style="outline: none; min-width: 160px;"
               data-token-search
               id="{{ $pickerId }}-search"
               role="combobox"
               aria-autocomplete="list"
               aria-haspopup="listbox"
               aria-expanded="false"
               aria-controls="{{ $pickerId }}-list"
               @if(filled($labelledBy)) aria-labelledby="{{ $labelledBy }}" @endif
               aria-label="{{ $ariaLabel ?: preg_replace('/[….\s]+$/u', '', $placeholder) }}"
               placeholder="{{ $placeholder }}"
               autocomplete="off">
    </div>

    <div class="border rounded p-3 bg-white shadow-sm d-none position-absolute start-0 end-0 mt-1"
        style="z-index: 1060; max-height: {{ $height }}; overflow-y: scroll;"
         id="{{ $pickerId }}-list"
         role="listbox"
         aria-label="{{ $ariaLabel ?: preg_replace('/[….\s]+$/u', '', $placeholder) }}"
         data-token-list></div>

    <div class="visually-hidden" role="status" aria-live="polite" data-token-status></div>

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
        const controlWrap = picker.querySelector('[data-token-control]');
        const statusNode = picker.querySelector('[data-token-status]');

        if (!searchInput || !selectedWrap || !listWrap || !hiddenInputs || !optionsNode || !controlWrap) {
            return;
        }

        const name = picker.dataset.name;
        const emptyMessage = picker.dataset.emptyMessage || 'No matches found.';
        const defaultPlaceholder = picker.dataset.placeholder || 'Search...';
        let disabledPlaceholder = picker.dataset.disabledPlaceholder || defaultPlaceholder;
        const showSelected = picker.dataset.showSelected === 'true';
        let emptySelectionLabel = (picker.dataset.emptySelectionLabel || '').trim();
        const defaultSelectedBadgeClass = (picker.dataset.selectedBadgeClass || '').trim();
        const options = parseJson(optionsNode, []);
        const initialSelected = new Set(parseJson({ textContent: picker.dataset.selected || '[]' }, []));
        const selected = new Set(Array.from(initialSelected));
        const single = picker.dataset.single === 'true';
        let allowedValues = null;
        let isDisabled = picker.dataset.disabled === 'true';

        let activeValue = null;

        function announce(message) {
            if (!statusNode) {
                return;
            }

            statusNode.textContent = '';
            setTimeout(function () {
                statusNode.textContent = message;
            }, 50);
        }

        function optionDomId(value) {
            return picker.id + '-option-' + String(value).replace(/[^A-Za-z0-9_-]/g, '-');
        }

        function setExpanded(expanded) {
            searchInput.setAttribute('aria-expanded', expanded ? 'true' : 'false');

            if (!expanded) {
                setActive(null);
            }
        }

        function setActive(value) {
            activeValue = value;

            listWrap.querySelectorAll('[role="option"]').forEach(function (row) {
                const isActive = value !== null && row.dataset.value === String(value);
                row.classList.toggle('token-option-active', isActive);

                if (isActive) {
                    searchInput.setAttribute('aria-activedescendant', row.id);
                }
            });

            if (value === null) {
                searchInput.removeAttribute('aria-activedescendant');
            }
        }

        function choose(value, label) {
            if (single) selected.clear();
            selected.add(String(value));
            renderSelected();
            renderList(true);
            writeHiddenInputs();
            picker.dispatchEvent(new CustomEvent('token-picker:change', { bubbles: true, detail: { user: true } }));
            announce(label + ' added');
            searchInput.focus();
            if (single) {
                searchInput.value = '';
                closeList();
            }
        }

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
                allBadge.className = 'badge ' + (defaultSelectedBadgeClass !== '' ? defaultSelectedBadgeClass : 'app-chip') + ' d-inline-flex align-items-center';
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
                badge.querySelector('button').setAttribute('aria-label', 'Remove ' + option.label);
                badge.querySelector('button').addEventListener('click', function () {
                    selected.delete(String(value));
                    renderSelected();
                    renderList();
                    writeHiddenInputs();
                    picker.dispatchEvent(new CustomEvent('token-picker:change', { bubbles: true, detail: { user: true } }));
                    announce(option.label + ' removed');
                });
                selectedWrap.appendChild(badge);
            });
        }

        function appendContextBadges(textWrap, opt) {
            const badgeClass = opt.contextBadgeClass || 'app-chip';
            const labels = Array.isArray(opt.contextLabels) && opt.contextLabels.length > 0
                ? opt.contextLabels
                : (opt.context ? [String(opt.context)] : []);

            labels.forEach(function (label) {
                const badge = document.createElement('span');
                badge.className = 'badge ' + badgeClass;
                if (single) {
                    badge.classList.add('text-wrap', 'text-start', 'mw-100');
                }
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

            if (single && list.length === 0) {
                closeList();
                return;
            }

            if (list.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'text-muted small';
                empty.textContent = emptyMessage;
                listWrap.appendChild(empty);
            } else {
                list.forEach(function (opt) {
                    const row = document.createElement('div');
                    row.id = optionDomId(opt.value);
                    row.className = 'token-option d-flex align-items-start gap-2 mb-1';
                    row.setAttribute('role', 'option');
                    row.setAttribute('aria-selected', 'false');
                    row.dataset.value = String(opt.value);
                    row.addEventListener('click', function () {
                        choose(opt.value, opt.label);
                    });

                    const box = document.createElement('span');
                    box.className = 'form-check-input d-inline-block flex-shrink-0 mt-1 ms-0';
                    box.setAttribute('aria-hidden', 'true');

                    const textWrap = document.createElement('div');
                    textWrap.className = 'form-check-label d-flex flex-wrap align-items-center gap-2';
                if (single) {
                    textWrap.classList.add('flex-grow-1', 'min-w-0');
                }

                    appendOptionPrimaryLabel(textWrap, opt);

                    appendContextBadges(textWrap, opt);

                    row.appendChild(box);
                    row.appendChild(textWrap);
                    listWrap.appendChild(row);
                });
            }

            if (forceOpen) {
                listWrap.classList.remove('d-none');
                setExpanded(true);
            }

            if (activeValue !== null) {
                setActive(listWrap.querySelector('[data-value="' + CSS.escape(String(activeValue)) + '"]') ? activeValue : null);
            }
        }

        function closeList() {
            listWrap.classList.add('d-none');
            setExpanded(false);
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
            (single ? values.slice(0, 1) : values).forEach(function (value) {
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
                if (single) selected.clear();
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

        picker.addEventListener('token-picker:set-empty-selection-label', function (event) {
            const detail = event.detail;

            if (typeof detail === 'string') {
                emptySelectionLabel = detail.trim();
            } else if (typeof detail === 'object' && detail !== null && typeof detail.label === 'string') {
                emptySelectionLabel = detail.label.trim();
            } else {
                emptySelectionLabel = '';
            }

            picker.dataset.emptySelectionLabel = emptySelectionLabel;
            renderSelected();
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

            setActive(null);
            renderList(true);

            const count = listWrap.querySelectorAll('[role="option"]').length;
            announce(count === 0 ? emptyMessage : count + (count === 1 ? ' option' : ' options') + ' available');
        });
        searchInput.addEventListener('keydown', function (event) {
            if (isDisabled) {
                return;
            }

            const isOpen = !listWrap.classList.contains('d-none');

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();

                if (!isOpen) {
                    renderList(true);
                }

                const rows = Array.from(listWrap.querySelectorAll('[role="option"]'));

                if (rows.length === 0) {
                    return;
                }

                const current = rows.findIndex(function (row) {
                    return row.dataset.value === activeValue;
                });
                const next = event.key === 'ArrowDown'
                    ? Math.min(current + 1, rows.length - 1)
                    : Math.max(current - 1, 0);

                setActive(rows[next].dataset.value);
                rows[next].scrollIntoView({ block: 'nearest' });

                return;
            }

            if (event.key === 'Enter') {
                // Enter must never submit the form from here; it adds the highlighted option instead.
                event.preventDefault();

                const row = isOpen && activeValue !== null
                    ? listWrap.querySelector('[data-value="' + CSS.escape(String(activeValue)) + '"]')
                    : null;

                if (row) {
                    choose(row.dataset.value, optionByValue(row.dataset.value)?.label || row.dataset.value);
                }

                return;
            }

            if (event.key === 'Escape' && isOpen) {
                // Close the list only; a surrounding drawer should stay open.
                event.stopPropagation();
                closeList();
            }
        });

        // Keep keyboard focus in the search field when the list is clicked or scrolled.
        listWrap.addEventListener('mousedown', function (event) {
            event.preventDefault();
        });

        picker.addEventListener('focusout', function (event) {
            if (!picker.contains(event.relatedTarget)) {
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
    document.addEventListener('repeater:row-added', function (event) {
        initTokenPickers(event.target);
    });
})();
</script>
@endonce
