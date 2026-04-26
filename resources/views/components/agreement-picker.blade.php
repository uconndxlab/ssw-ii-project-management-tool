@props([
    'pickerId',
    'name',
    'agreements' => collect(),
    'selectedIds' => [],
    'searchPlaceholder' => 'Search agreements...',
    'emptyMessage' => 'No agreements available.',
    'height' => '300px',
])

@php
    $selectedIds = collect($selectedIds)->map(fn ($id) => (int) $id)->toArray();
@endphp

<div class="agreement-picker" data-agreement-picker id="{{ $pickerId }}">
    <div class="mb-2">
        <input
            type="text"
            class="form-control"
            placeholder="{{ $searchPlaceholder }}"
            data-agreement-picker-search
            autocomplete="off"
        >
    </div>

    <div class="border rounded p-3" style="max-height: {{ $height }}; overflow-y: auto;" data-agreement-picker-list>
        @forelse($agreements as $agreement)
            @php
                $searchText = strtolower(trim($agreement->name));
            @endphp

            <div
                class="form-check mb-2"
                data-agreement-picker-item
                data-agreement-search="{{ $searchText }}"
            >
                <input
                    class="form-check-input"
                    type="checkbox"
                    name="{{ $name }}"
                    value="{{ $agreement->id }}"
                    id="{{ $pickerId }}_agreement_{{ $agreement->id }}"
                    {{ in_array((int) $agreement->id, $selectedIds, true) ? 'checked' : '' }}
                >
                <label class="form-check-label" for="{{ $pickerId }}_agreement_{{ $agreement->id }}">
                    {{ $agreement->name }}
                </label>
            </div>
        @empty
            <small class="text-muted d-block">{{ $emptyMessage }}</small>
        @endforelse

        <small
            class="text-muted d-none"
            data-agreement-picker-no-results
        >
            No agreements match your search.
        </small>
    </div>
</div>

@once
    <script>
        function applyAgreementPickerFilter(picker) {
            const searchInput = picker.querySelector('[data-agreement-picker-search]');
            const items = picker.querySelectorAll('[data-agreement-picker-item]');
            const noResults = picker.querySelector('[data-agreement-picker-no-results]');
            const term = (searchInput?.value || '').trim().toLowerCase();

            let visibleCount = 0;

            items.forEach(function (item) {
                const haystack = item.dataset.agreementSearch || '';
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

        function initializeAgreementPicker(root) {
            const pickers = (root || document).querySelectorAll('[data-agreement-picker]');

            pickers.forEach(function (picker) {
                if (picker.dataset.agreementPickerInitialized === 'true') {
                    return;
                }

                const searchInput = picker.querySelector('[data-agreement-picker-search]');

                if (!searchInput) {
                    return;
                }

                searchInput.addEventListener('input', function () {
                    applyAgreementPickerFilter(picker);
                });

                searchInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        applyAgreementPickerFilter(picker);
                    }
                });

                picker.dataset.agreementPickerInitialized = 'true';
                applyAgreementPickerFilter(picker);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            initializeAgreementPicker(document);
        });

        document.body.addEventListener('htmx:afterSwap', function (event) {
            initializeAgreementPicker(event.target);
        });
    </script>
@endonce
