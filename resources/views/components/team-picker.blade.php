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

    <div class="border rounded p-3" style="max-height: {{ $height }}; overflow-y: auto;" data-team-picker-list>
        @forelse($teams as $team)
            @php
                $searchText = strtolower(trim($team->name));
            @endphp

            <div
                class="form-check mb-2"
                data-team-picker-item
                data-team-search="{{ $searchText }}"
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

                picker.dataset.teamPickerInitialized = 'true';
                applyTeamPickerFilter(picker);
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
