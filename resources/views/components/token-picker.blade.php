@props([
    'pickerId',
    'name',
    'items' => collect(),
    'options' => null,
    'selectedIds' => [],
    'placeholder' => 'Search…',
    'labelKey' => 'name',
    'valueKey' => 'id',
    'searchKey' => null,
    'emptyMessage' => 'No matches found.',
    'openOnFocus' => true,
    'showSelected' => true,
    'height' => '300px',
    'disabled' => false,
    'disabledPlaceholder' => null,
    'emptySelectionLabel' => null,
    'entity' => null,
    'entityKind' => null,
    'labelledBy' => null,
    'ariaLabel' => null,
    'single' => false,
])

@php
    use App\Support\EntityBadge;

    $selectedIds = collect($selectedIds)->map(fn ($id) => (string) $id)->values()->all();

    $searchKey = $searchKey ?: $labelKey;

    $resolvedEntity = filled($entity) ? $entity : $entityKind;

    $entityBadgeClass = filled($resolvedEntity)
        ? EntityBadge::relationClasses($resolvedEntity)
        : null;

    $normalizedOptions = collect($options ?? $items)->map(function ($item) use ($labelKey, $valueKey, $searchKey, $entityBadgeClass, $resolvedEntity) {
        $optionEntity = data_get($item, 'entity', $resolvedEntity);
        $optionBadgeClass = filled($optionEntity)
            ? EntityBadge::relationClasses($optionEntity)
            : null;

        return [
            'value' => (string) data_get($item, $valueKey),
            'label' => (string) data_get($item, $labelKey),
            'search' => strtolower((string) data_get($item, $searchKey, data_get($item, $labelKey))),
            'entity' => $optionEntity,
            'context' => data_get($item, 'context'),
            'contextLabels' => array_values(array_filter(
                data_get($item, 'contextLabels') ?? (data_get($item, 'context') ? [data_get($item, 'context')] : []),
                fn ($label) => $label !== null && $label !== ''
            )),
            'contextBadgeClass' => data_get($item, 'contextBadgeClass', $optionBadgeClass ?? $entityBadgeClass ?? 'app-chip'),
            'meta' => filled(data_get($item, 'meta'))
                ? (string) data_get($item, 'meta')
                : (filled(data_get($item, 'po_number')) ? (string) data_get($item, 'po_number') : null),
            'selectedBadgeClass' => data_get($item, 'selectedBadgeClass', $optionBadgeClass ?? $entityBadgeClass),
        ];
    })->filter(fn ($option) => $option['value'] !== '')->values()->all();

    $errorKey = \Illuminate\Support\Str::before($name, '[');
    $hasError = collect($errors->keys())->contains(
        fn ($key) => $key === $errorKey || str_starts_with((string) $key, $errorKey . '.')
    );
@endphp

<div id="{{ $pickerId }}"
     class="token-picker position-relative"
     data-token-picker
     data-name="{{ $name }}"
     data-empty-message="{{ $emptyMessage }}"
     data-placeholder="{{ $placeholder }}"
     data-disabled-placeholder="{{ $disabledPlaceholder ?? $placeholder }}"
     data-open-on-focus="{{ $openOnFocus ? 'true' : 'false' }}"
     data-disabled="{{ $disabled ? 'true' : 'false' }}"
     data-show-selected="{{ $showSelected ? 'true' : 'false' }}"
     data-single="{{ $single ? 'true' : 'false' }}"
     data-empty-selection-label="{{ $emptySelectionLabel ?? '' }}"
     data-selected-badge-class="{{ $entityBadgeClass ?? '' }}"
     data-selected='@json($selectedIds)'>
    <div class="d-flex flex-wrap gap-1 mb-2 {{ $showSelected ? '' : 'd-none' }}" data-token-selected></div>

    <div class="form-control d-flex align-items-center py-1{{ $hasError ? ' is-invalid' : '' }}" data-token-control>
        <input type="text"
               class="border-0 flex-grow-1"
               style="outline: none; min-width: 160px;"
               data-token-search
               id="{{ $pickerId }}-search"
               role="combobox"
               aria-autocomplete="list"
               aria-haspopup="listbox"
               aria-expanded="false"
               aria-controls="{{ $pickerId }}-list"
               @if(filled($labelledBy)) aria-labelledby="{{ $labelledBy }}" @endif
               aria-label="{{ $ariaLabel ?: preg_replace('/[….\s]+$/u', '', $placeholder) }}"
               placeholder="{{ $placeholder }}"
               autocomplete="off">
    </div>

    <div class="app-picker-menu p-3 d-none position-absolute start-0 end-0 mt-1"
        style="z-index: 1060; max-height: {{ $height }}; overflow-y: scroll;"
         id="{{ $pickerId }}-list"
         role="listbox"
         aria-label="{{ $ariaLabel ?: preg_replace('/[….\s]+$/u', '', $placeholder) }}"
         data-token-list></div>

    <div class="visually-hidden" role="status" aria-live="polite" data-token-status></div>

    <div data-token-inputs></div>

    <script type="application/json" data-token-options>
        @json($normalizedOptions)
    </script>
</div>
