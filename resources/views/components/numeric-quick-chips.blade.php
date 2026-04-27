@props([
    'for',
    'values' => [0.5, 1, 2, 4],
])

<div class="d-flex flex-wrap gap-1 mt-2" data-chip-group data-target="{{ $for }}">
    @foreach($values as $value)
        <button type="button" class="btn btn-sm btn-outline-secondary" data-chip-value="{{ $value }}">
            {{ $value }}
        </button>
    @endforeach
</div>

@once
<script>
(function () {
    function initChipGroups(root) {
        (root || document).querySelectorAll('[data-chip-group]').forEach(function (group) {
            if (group.dataset.initialized === 'true') {
                return;
            }

            const targetId = group.dataset.target;
            const target = document.getElementById(targetId);
            if (!target) {
                return;
            }

            group.querySelectorAll('[data-chip-value]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    target.value = chip.dataset.chipValue;
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                    target.focus();
                    target.select?.();
                });
            });

            group.dataset.initialized = 'true';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initChipGroups(document);
    });

    document.body.addEventListener('htmx:afterSwap', function (event) {
        initChipGroups(event.target);
    });
})();
</script>
@endonce
