const select = document.querySelector('[data-duration-unit-select]');
const form = select?.closest('form');
const input = form?.querySelector('#duration_value');

if (select && input) {
    const source = form.querySelector('#duration-source');
    const activityType = form.querySelector('#activity_type_id');

    function refreshDurationField() {
        input.disabled = select.value === 'none';

        if (source && activityType) {
            const type = activityType.selectedOptions[0];
            const label = type?.dataset.durationLabel;
            source.textContent = label
                ? (select.value === 'none' ? 'No duration here: uses ' : 'Overrides ') + type.textContent.trim() + "'s " + label + '.'
                : 'No duration inherits the activity type’s duration. A duration set here overrides the type’s.';
        }
    }

    activityType?.addEventListener('change', refreshDurationField);
    select.addEventListener('change', refreshDurationField);
    refreshDurationField();
}
