@props(['name', 'id', 'label', 'value' => '', 'calculated' => '', 'overridden' => false, 'overrideName' => null, 'disabled' => false, 'revertLabel' => 'Use calculated'])
<x-form-field :label="$label" :for="$id" :name="str_replace(['][', '[', ']'], ['.', '.', ''], $name)">
    <div data-date-override data-calculated="{{ $calculated }}" class="d-flex flex-wrap align-items-center gap-2">
        @if($overrideName)<input type="hidden" name="{{ $overrideName }}" value="{{ $overridden ? '1' : '0' }}" data-date-flag>@endif
        <span data-date-display @if($overridden) hidden @endif>{{ ($value ?: $calculated) ? \Illuminate\Support\Carbon::parse($value ?: $calculated)->format('M j, Y') : 'Not set' }}</span>
        <input type="date" class="form-control w-auto" id="{{ $id }}" name="{{ $name }}" value="{{ $value ?: $calculated }}"
               data-date-input @if(! $overridden) hidden readonly @endif @disabled($disabled)>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-date-change @if($overridden || $disabled) hidden @endif>Change<span class="visually-hidden"> {{ strtolower($label) }}</span></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-date-revert @if(! $overridden || $disabled) hidden @endif>{{ $revertLabel }}</button>
    </div>
</x-form-field>
@once
@push('scripts')
<script>
document.addEventListener('click', event => {
    const button = event.target.closest('[data-date-change], [data-date-revert]');
    if (!button) return;
    const field = button.closest('[data-date-override]');
    const editing = button.hasAttribute('data-date-change');
    const input = field.querySelector('[data-date-input]');
    input.hidden = !editing;
    input.readOnly = !editing;
    field.querySelector('[data-date-display]').hidden = editing;
    field.querySelector('[data-date-change]').hidden = editing;
    field.querySelector('[data-date-revert]').hidden = !editing;
    const flag = field.querySelector('[data-date-flag]');
    if (flag) flag.value = editing ? '1' : '0';
    if (!editing) input.value = field.dataset.calculated;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    (editing ? input : field.querySelector('[data-date-change]')).focus();
});
</script>
@endpush
@endonce
