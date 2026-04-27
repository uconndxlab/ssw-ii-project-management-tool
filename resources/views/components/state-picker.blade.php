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

    <div class="border rounded p-3" style="max-height: {{ $height }}; overflow-y: auto;" data-state-picker-list>
        @forelse($states as $state)
            @php
                $searchText = strtolower(trim($state->name . ' ' . ($state->abbreviation ?? '')));
            @endphp

            <div
                class="form-check mb-2"
                data-state-picker-item
                data-state-search="{{ $searchText }}"
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

                picker.dataset.statePickerInitialized = 'true';
                applyStatePickerFilter(picker);
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
