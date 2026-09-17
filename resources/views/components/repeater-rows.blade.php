@props([
    'name',
    'rows' => [],
    'nextIndex' => null,
    'indexToken' => '__INDEX__',
    'addLabel' => 'Add row',
    'template' => null,
])

@php
    $resolvedNextIndex = $nextIndex ?? (is_countable($rows) ? count($rows) : 0);
@endphp

<div {{ $attributes->class(['x-repeater']) }}
     data-repeater
     data-repeater-name="{{ $name }}"
     data-index-token="{{ $indexToken }}"
     data-next-index="{{ $resolvedNextIndex }}">
    <div class="x-repeater-rows d-flex flex-column gap-2" data-repeater-rows>
        {{ $slot }}
    </div>

    <template data-repeater-template>{!! $template ?? '' !!}</template>

    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-repeater-add>
        <i class="bi bi-plus-lg"></i> {{ $addLabel }}
    </button>
</div>

@once
<script>
(function () {
    // Generalized multi-field repeater: add/remove rows, mark existing rows for deletion instead of
    // stripping them from the DOM. Handles arbitrarily nested repeaters via event delegation and
    // :scope-qualified lookups, so a newly cloned row's own nested repeater works with no extra wiring.
    function directTemplate(wrapper) {
        return Array.from(wrapper.children).find(function (el) {
            return el.tagName === 'TEMPLATE' && el.hasAttribute('data-repeater-template');
        });
    }

    function directRowsContainer(wrapper) {
        return Array.from(wrapper.children).find(function (el) {
            return el.hasAttribute('data-repeater-rows');
        });
    }

    document.addEventListener('click', function (e) {
        const addBtn = e.target.closest('[data-repeater-add]');
        if (addBtn) {
            e.preventDefault();
            const wrapper = addBtn.closest('[data-repeater]');
            if (!wrapper) return;

            const template = directTemplate(wrapper);
            const rowsContainer = directRowsContainer(wrapper);
            if (!template || !rowsContainer) return;

            const token = wrapper.getAttribute('data-index-token') || '__INDEX__';
            const nextIndex = parseInt(wrapper.getAttribute('data-next-index') || '0', 10);

            const temp = document.createElement('div');
            temp.appendChild(template.content.cloneNode(true));
            temp.innerHTML = temp.innerHTML.split(token).join(String(nextIndex));

            Array.from(temp.children).forEach(function (child) {
                rowsContainer.appendChild(child);
            });

            wrapper.setAttribute('data-next-index', String(nextIndex + 1));
            return;
        }

        const removeBtn = e.target.closest('[data-repeater-remove]');
        if (removeBtn) {
            e.preventDefault();
            const row = removeBtn.closest('[data-repeater-row]');
            if (!row) return;

            if (row.getAttribute('data-existing') === '1') {
                const deleteInput = row.querySelector('[data-repeater-delete]');
                if (deleteInput) deleteInput.value = '1';
                row.style.display = 'none';
            } else {
                row.remove();
            }
        }
    });
})();
</script>
@endonce
