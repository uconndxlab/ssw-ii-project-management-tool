@props([
    'name',
    'rows' => [],
    'nextIndex' => null,
    'indexToken' => '__INDEX__',
    'addLabel' => 'Add row',
    'template' => null,
    'sortable' => true,
    'variant' => 'well',
    'scroll' => true,
])

@php
    // Rows removed before a failed save leave gaps, so continue past the highest kept number.
    $resolvedNextIndex = $nextIndex ?? collect($rows)->keys()->map(fn ($key) => (int) $key)->push(-1)->max() + 1;
    $isNested = in_array($variant, ['nested', 'flat'], true);
    $isFlat = $variant === 'flat';
    $rowCount = is_countable($rows) ? count($rows) : 0;
@endphp

<div {{ $attributes->class(['repeater', $isNested ? 'repeater--nested' : 'repeater--well']) }}
     data-repeater
     data-repeater-name="{{ $name }}"
     data-index-token="{{ $indexToken }}"
     data-next-index="{{ $resolvedNextIndex }}"
     @if($sortable) data-repeater-sortable @endif>
    <div class="repeater-rows{{ $isFlat ? ' repeater-rows--flat' : '' }}{{ $scroll ? '' : ' repeater-rows--unbounded' }}{{ $rowCount === 0 ? ' is-empty' : '' }}"
         data-repeater-rows>
        {{ $slot }}
    </div>

    <template data-repeater-template>{!! $template ?? '' !!}</template>

    <button type="button" class="repeater-add" data-repeater-add>
        <i class="bi bi-plus-lg"></i>
        <span>{{ $addLabel }}</span>
    </button>
</div>
