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

    <div data-user-picker-chips class="d-flex flex-wrap gap-1 mb-2"></div>

    <div class="border rounded p-3" style="max-height: {{ $height }}; overflow-y: auto;" data-user-picker-list>
        @forelse($users as $user)
            @php
                $searchText = strtolower(trim($user->name . ' ' . ($user->email ?? '') . ' ' . ($user->role ?? '')));
                $chipLabel = $user->name . ($showRole && !empty($user->role) ? ' (' . ucfirst($user->role) . ')' : '');
            @endphp

            <div
                class="form-check mb-2"
                data-user-picker-item
                data-user-search="{{ $searchText }}"
                data-user-label="{{ $chipLabel }}"
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
        function syncUserPickerChips(picker) {
            const chipsContainer = picker.querySelector('[data-user-picker-chips]');
            if (!chipsContainer) return;

            chipsContainer.innerHTML = '';

            picker.querySelectorAll('[data-user-picker-item] input[type="checkbox"]:checked').forEach(function (cb) {
                const item = cb.closest('[data-user-picker-item]');
                const label = item ? item.dataset.userLabel : cb.value;

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
                    syncUserPickerChips(picker);
                });

                chip.appendChild(btn);
                chipsContainer.appendChild(chip);
            });
        }

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

                picker.querySelectorAll('[data-user-picker-item] input[type="checkbox"]').forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        syncUserPickerChips(picker);
                    });
                });

                picker.dataset.userPickerInitialized = 'true';
                applyUserPickerFilter(picker);
                syncUserPickerChips(picker);
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