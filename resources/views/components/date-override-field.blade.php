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
