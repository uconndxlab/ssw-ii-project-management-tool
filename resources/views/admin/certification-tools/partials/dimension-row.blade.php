@php
    $dimId = $row['id'] ?? null;
    $isExisting = filled($dimId);
    $options = $row['options'] ?? [];
    $optionCount = is_countable($options) ? count($options) : 0;
@endphp
<div class="repeater-row-card" data-repeater-row data-repeater-collapsible
     data-existing="{{ $isExisting ? '1' : '0' }}"
     data-repeater-mode="{{ $isExisting ? 'display' : 'edit' }}">
    @if($isExisting)
        <input type="hidden" name="dimensions[{{ $dimIndex }}][id]" value="{{ $dimId }}">
    @endif
    <input type="hidden" name="dimensions[{{ $dimIndex }}][_delete]" value="0" data-repeater-delete>

    <div class="repeater-row-toolbar" data-row-display-click>
        <span class="repeater-row-drag" data-repeater-drag title="Drag to reorder" aria-hidden="true">
            <i class="bi bi-grip-vertical"></i>
        </span>
        <div class="repeater-row-summary">
            <span class="repeater-row-title" data-summary-value="name" data-summary-fallback="Untitled dimension">{{ filled($row['name'] ?? null) ? $row['name'] : 'Untitled dimension' }}</span>
            <span class="repeater-row-meta"><span data-summary-count data-summary-count-root="[data-options-list]">{{ $optionCount }}</span>&nbsp;options</span>
        </div>
        <div class="repeater-row-actions">
            <button type="button" class="repeater-icon-btn" data-row-edit title="{{ $isExisting ? 'Edit dimension' : 'Collapse dimension' }}">
                <i class="bi bi-pencil" data-icon-edit></i>
                <i class="bi bi-chevron-up" data-icon-collapse></i>
            </button>
            <button type="button" class="repeater-icon-btn repeater-icon-btn-danger" data-repeater-remove title="Remove dimension">
                <i class="bi bi-x-circle"></i>
            </button>
        </div>
    </div>

    <div class="repeater-row-body" data-row-edit-fields>
        <div class="mb-3">
            <label class="form-label form-label-sm mb-1">Dimension name</label>
            <input type="text" class="form-control form-control-sm" data-summary-source="name"
                   name="dimensions[{{ $dimIndex }}][name]" value="{{ $row['name'] ?? '' }}"
                   placeholder="e.g. Phase">
        </div>

        <div data-options-list>
            <div class="d-flex align-items-baseline justify-content-between gap-2 mb-1">
                <label class="form-label form-label-sm mb-0">Options</label>
                <span class="repeater-row-meta"><span data-summary-count data-summary-count-root="[data-options-list]">{{ $optionCount }}</span></span>
            </div>
            <x-repeater-rows
                name="dimensions[{{ $dimIndex }}][options]"
                :rows="$options"
                index-token="__OPT_INDEX__"
                add-label="Add option"
                variant="flat"
                :template="view('admin.certification-tools.partials.dimension-option-row', ['row' => [], 'dimIndex' => $dimIndex, 'optIndex' => '__OPT_INDEX__'])->render()"
            >
                @foreach($options as $optIndex => $option)
                    @include('admin.certification-tools.partials.dimension-option-row', ['row' => $option, 'dimIndex' => $dimIndex, 'optIndex' => $optIndex])
                @endforeach
            </x-repeater-rows>
        </div>
    </div>
</div>
