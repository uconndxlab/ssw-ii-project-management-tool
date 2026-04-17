@props([
    'pickerId',
    'name',
    'users' => collect(),
    'selectedIds' => [],
    'searchPlaceholder' => 'Search users...',
    'emptyMessage' => 'No users available.',
    'height' => '300px',
    'showRole' => false,
])

@php
    $selectedIds = collect($selectedIds)->map(fn ($id) => (int) $id)->toArray();
@endphp

<div class="user-picker" data-user-picker id="{{ $pickerId }}">
    <div class="mb-2">
        <input
            type="text"
            class="form-control"
            placeholder="{{ $searchPlaceholder }}"
            data-user-picker-search
            autocomplete="off"
        >
    </div>

    <div class="border rounded p-3" style="max-height: {{ $height }}; overflow-y: auto;" data-user-picker-list>
        @forelse($users as $user)
            @php
                $searchText = strtolower(trim($user->name . ' ' . ($user->email ?? '') . ' ' . ($user->role ?? '')));
            @endphp

            <div
                class="form-check mb-2"
                data-user-picker-item
                data-user-search="{{ $searchText }}"
            >
                <input
                    class="form-check-input"
                    type="checkbox"
                    name="{{ $name }}"
                    value="{{ $user->id }}"
                    id="{{ $pickerId }}_user_{{ $user->id }}"
                    {{ in_array((int) $user->id, $selectedIds, true) ? 'checked' : '' }}
                >
                <label class="form-check-label" for="{{ $pickerId }}_user_{{ $user->id }}">
                    {{ $user->name }}
                    @if($showRole && !empty($user->role))
                        ({{ ucfirst($user->role) }})
                    @endif
                </label>
            </div>
        @empty
            <small class="text-muted d-block">{{ $emptyMessage }}</small>
        @endforelse

        <small
            class="text-muted d-none"
            data-user-picker-no-results
        >
            No users match your search.
        </small>
    </div>
</div>

@once
    <script>
        function applyUserPickerFilter(picker) {
            const searchInput = picker.querySelector('[data-user-picker-search]');
            const items = picker.querySelectorAll('[data-user-picker-item]');
            const noResults = picker.querySelector('[data-user-picker-no-results]');
            const term = (searchInput?.value || '').trim().toLowerCase();

            let visibleCount = 0;

            items.forEach(function (item) {
                const haystack = item.dataset.userSearch || '';
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

        function initializeUserPicker(root) {
            const pickers = (root || document).querySelectorAll('[data-user-picker]');

            pickers.forEach(function (picker) {
                if (picker.dataset.userPickerInitialized === 'true') {
                    return;
                }

                const searchInput = picker.querySelector('[data-user-picker-search]');

                if (!searchInput) {
                    return;
                }

                searchInput.addEventListener('input', function () {
                    applyUserPickerFilter(picker);
                });

                searchInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        applyUserPickerFilter(picker);
                    }
                });

                picker.dataset.userPickerInitialized = 'true';
                applyUserPickerFilter(picker);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            initializeUserPicker(document);
        });

        document.body.addEventListener('htmx:afterSwap', function (event) {
            initializeUserPicker(event.target);
        });
    </script>
@endonce