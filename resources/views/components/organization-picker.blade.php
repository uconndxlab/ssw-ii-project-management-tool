@props([
    'pickerId',
    'name',
    'organizations' => collect(),
    'selectedIds' => [],
    'searchPlaceholder' => 'Search organizations...',
    'emptyMessage' => 'No organizations available.',
    'height' => '300px',
])

@php
    $selectedIds = collect($selectedIds)->map(fn ($id) => (int) $id)->toArray();
@endphp

<div class="organization-picker" data-organization-picker id="{{ $pickerId }}">
    <div class="mb-2">
        <input
            type="text"
            class="form-control"
            placeholder="{{ $searchPlaceholder }}"
            data-organization-picker-search
            autocomplete="off"
        >
    </div>

    <div class="border rounded p-3" style="max-height: {{ $height }}; overflow-y: auto;" data-organization-picker-list>
        @forelse($organizations as $organization)
            @php
                $searchText = strtolower(trim($organization->name));
            @endphp

            <div
                class="form-check mb-2"
                data-organization-picker-item
                data-organization-search="{{ $searchText }}"
            >
                <input
                    class="form-check-input"
                    type="checkbox"
                    name="{{ $name }}"
                    value="{{ $organization->id }}"
                    id="{{ $pickerId }}_organization_{{ $organization->id }}"
                    {{ in_array((int) $organization->id, $selectedIds, true) ? 'checked' : '' }}
                >
                <label class="form-check-label" for="{{ $pickerId }}_organization_{{ $organization->id }}">
                    {{ $organization->name }}
                </label>
            </div>
        @empty
            <small class="text-muted d-block">{{ $emptyMessage }}</small>
        @endforelse

        <small
            class="text-muted d-none"
            data-organization-picker-no-results
        >
            No organizations match your search.
        </small>
    </div>
</div>

@once
    <script>
        function applyOrganizationPickerFilter(picker) {
            const searchInput = picker.querySelector('[data-organization-picker-search]');
            const items = picker.querySelectorAll('[data-organization-picker-item]');
            const noResults = picker.querySelector('[data-organization-picker-no-results]');
            const term = (searchInput?.value || '').trim().toLowerCase();

            let visibleCount = 0;

            items.forEach(function (item) {
                const haystack = item.dataset.organizationSearch || '';
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

        function initializeOrganizationPicker(root) {
            const pickers = (root || document).querySelectorAll('[data-organization-picker]');

            pickers.forEach(function (picker) {
                if (picker.dataset.organizationPickerInitialized === 'true') {
                    return;
                }

                const searchInput = picker.querySelector('[data-organization-picker-search]');

                if (!searchInput) {
                    return;
                }

                searchInput.addEventListener('input', function () {
                    applyOrganizationPickerFilter(picker);
                });

                searchInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        applyOrganizationPickerFilter(picker);
                    }
                });

                picker.dataset.organizationPickerInitialized = 'true';
                applyOrganizationPickerFilter(picker);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            initializeOrganizationPicker(document);
        });

        document.body.addEventListener('htmx:afterSwap', function (event) {
            initializeOrganizationPicker(event.target);
        });
    </script>
@endonce
