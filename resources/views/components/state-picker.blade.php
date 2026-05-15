@props([
    'pickerId',
    'name',
    'states' => collect(),
    'selectedIds' => [],
    'searchPlaceholder' => 'Search states...',
    'emptyMessage' => 'No states available.',
    'height' => '300px',
])

@php
    $selectedIds = collect($selectedIds)->map(fn ($id) => (int) $id)->toArray();
@endphp

<div class="state-picker" data-state-picker id="{{ $pickerId }}">
    <div class="mb-2">
        <input
            type="text"
            class="form-control"
            placeholder="{{ $searchPlaceholder }}"
            data-state-picker-search
            autocomplete="off"
        >
    </div>

    <div data-state-picker-chips class="d-flex flex-wrap gap-1 mb-2"></div>

    <div class="border rounded p-3" style="max-height: {{ $height }}; overflow-y: auto;" data-state-picker-list>
        @forelse($states as $state)
            @php
                $searchText = strtolower(trim($state->name . ' ' . ($state->abbreviation ?? '')));
            @endphp

            <div
                class="form-check mb-2"
                data-state-picker-item
                data-state-search="{{ $searchText }}"
                data-state-label="{{ $state->name }}"
            >
                <input
                    class="form-check-input"
                    type="checkbox"
                    name="{{ $name }}"
                    value="{{ $state->id }}"
                    id="{{ $pickerId }}_state_{{ $state->id }}"
                    {{ in_array((int) $state->id, $selectedIds, true) ? 'checked' : '' }}
                >
                <label class="form-check-label" for="{{ $pickerId }}_state_{{ $state->id }}">
                    {{ $state->name }}
                </label>
            </div>
        @empty
            <small class="text-muted d-block">{{ $emptyMessage }}</small>
        @endforelse

        <small
            class="text-muted d-none"
            data-state-picker-no-results
        >
            No states match your search.
        </small>
    </div>
</div>

@once
    <script>
        function syncStatePickerChips(picker) {
            const chipsContainer = picker.querySelector('[data-state-picker-chips]');
            if (!chipsContainer) return;

            chipsContainer.innerHTML = '';

            picker.querySelectorAll('[data-state-picker-item] input[type="checkbox"]:checked').forEach(function (cb) {
                const item = cb.closest('[data-state-picker-item]');
                const label = item ? item.dataset.stateLabel : cb.value;

                const chip = document.createElement('span');
                chip.className = 'badge rounded-pill d-inline-flex align-items-center gap-1 px-2 py-1';
                chip.style.cssText = 'background-color:#0d6efd22;color:#0d6efd;border:1px solid #0d6efd55;font-size:.75rem;font-weight:500;cursor:default;';
                chip.innerHTML = '<span>' + label + '</span>';

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.setAttribute('aria-label', 'Remove ' + label);
                btn.style.cssText = 'background:none;border:none;padding:0;line-height:1;cursor:pointer;color:#0d6efd;font-size:.85rem;';
                btn.innerHTML = '&times;';
                btn.addEventListener('click', function () {
                    cb.checked = false;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                    syncStatePickerChips(picker);
                });

                chip.appendChild(btn);
                chipsContainer.appendChild(chip);
            });
        }

        function applyStatePickerFilter(picker) {
            const searchInput = picker.querySelector('[data-state-picker-search]');
            const items = picker.querySelectorAll('[data-state-picker-item]');
            const noResults = picker.querySelector('[data-state-picker-no-results]');
            const term = (searchInput?.value || '').trim().toLowerCase();

            let visibleCount = 0;

            items.forEach(function (item) {
                const haystack = item.dataset.stateSearch || '';
                const match = term === '' || haystack.includes(term);

                item.classList.toggle('d-none', !match);

                if (match) {
                    visibleCount++;
                }
            });

            if (noResults) {
                noResults.classList.toggle('d-none', visibleCount > 0 || items.length === 0);
            }
        }

        function initializeStatePicker(root) {
            const pickers = (root || document).querySelectorAll('[data-state-picker]');

            pickers.forEach(function (picker) {
                if (picker.dataset.statePickerInitialized === 'true') {
                    return;
                }

                const searchInput = picker.querySelector('[data-state-picker-search]');

                if (!searchInput) {
                    return;
                }

                searchInput.addEventListener('input', function () {
                    applyStatePickerFilter(picker);
                });

                searchInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        applyStatePickerFilter(picker);
                    }
                });

                picker.querySelectorAll('[data-state-picker-item] input[type="checkbox"]').forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        syncStatePickerChips(picker);
                    });
                });

                picker.dataset.statePickerInitialized = 'true';
                applyStatePickerFilter(picker);
                syncStatePickerChips(picker);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            initializeStatePicker(document);
        });

        document.body.addEventListener('htmx:afterSwap', function (event) {
            initializeStatePicker(event.target);
        });
    </script>
@endonce
