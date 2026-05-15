@props([
    'pickerId',
    'name',
    'teams' => collect(),
    'selectedIds' => [],
    'searchPlaceholder' => 'Search teams...',
    'emptyMessage' => 'No teams available.',
    'height' => '300px',
])

@php
    $selectedIds = collect($selectedIds)->map(fn ($id) => (int) $id)->toArray();
@endphp

<div class="team-picker" data-team-picker id="{{ $pickerId }}">
    <div class="mb-2">
        <input
            type="text"
            class="form-control"
            placeholder="{{ $searchPlaceholder }}"
            data-team-picker-search
            autocomplete="off"
        >
    </div>

    <div data-team-picker-chips class="d-flex flex-wrap gap-1 mb-2"></div>

    <div class="border rounded p-3" style="max-height: {{ $height }}; overflow-y: auto;" data-team-picker-list>
        @forelse($teams as $team)
            @php
                $searchText = strtolower(trim($team->name));
            @endphp

            <div
                class="form-check mb-2"
                data-team-picker-item
                data-team-search="{{ $searchText }}"
                data-team-label="{{ $team->name }}"
            >
                <input
                    class="form-check-input"
                    type="checkbox"
                    name="{{ $name }}"
                    value="{{ $team->id }}"
                    id="{{ $pickerId }}_team_{{ $team->id }}"
                    {{ in_array((int) $team->id, $selectedIds, true) ? 'checked' : '' }}
                >
                <label class="form-check-label" for="{{ $pickerId }}_team_{{ $team->id }}">
                    {{ $team->name }}
                    @if(!$team->active)
                        <span class="badge bg-secondary">Inactive</span>
                    @endif
                </label>
            </div>
        @empty
            <small class="text-muted d-block">{{ $emptyMessage }}</small>
        @endforelse

        <small
            class="text-muted d-none"
            data-team-picker-no-results
        >
            No teams match your search.
        </small>
    </div>
</div>

@once
    <script>
        function syncTeamPickerChips(picker) {
            const chipsContainer = picker.querySelector('[data-team-picker-chips]');
            if (!chipsContainer) return;

            chipsContainer.innerHTML = '';

            picker.querySelectorAll('[data-team-picker-item] input[type="checkbox"]:checked').forEach(function (cb) {
                const item = cb.closest('[data-team-picker-item]');
                const label = item ? item.dataset.teamLabel : cb.value;

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
                    syncTeamPickerChips(picker);
                });

                chip.appendChild(btn);
                chipsContainer.appendChild(chip);
            });
        }

        function applyTeamPickerFilter(picker) {
            const searchInput = picker.querySelector('[data-team-picker-search]');
            const items = picker.querySelectorAll('[data-team-picker-item]');
            const noResults = picker.querySelector('[data-team-picker-no-results]');
            const term = (searchInput?.value || '').trim().toLowerCase();

            let visibleCount = 0;

            items.forEach(function (item) {
                const haystack = item.dataset.teamSearch || '';
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

        function initializeTeamPicker(root) {
            const pickers = (root || document).querySelectorAll('[data-team-picker]');

            pickers.forEach(function (picker) {
                if (picker.dataset.teamPickerInitialized === 'true') {
                    return;
                }

                const searchInput = picker.querySelector('[data-team-picker-search]');

                if (!searchInput) {
                    return;
                }

                searchInput.addEventListener('input', function () {
                    applyTeamPickerFilter(picker);
                });

                searchInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        applyTeamPickerFilter(picker);
                    }
                });

                picker.querySelectorAll('[data-team-picker-item] input[type="checkbox"]').forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        syncTeamPickerChips(picker);
                    });
                });

                picker.dataset.teamPickerInitialized = 'true';
                applyTeamPickerFilter(picker);
                syncTeamPickerChips(picker);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            initializeTeamPicker(document);
        });

        document.body.addEventListener('htmx:afterSwap', function (event) {
            initializeTeamPicker(event.target);
        });
    </script>
@endonce
