@php
    $sectionId = $row['id'] ?? null;
    $isExisting = filled($sectionId);
    $isUsed = $isExisting && in_array((int) $sectionId, $usedOptionIds ?? [], true);
    $isScored = filter_var($row['scored'] ?? ($defaultScored ?? true), FILTER_VALIDATE_BOOLEAN);
    $field = 'sections['.$sectionIndex.']';
    $control = 'section-'.$sectionIndex;
@endphp
<div class="repeater-row-card repeater-row-inline" data-repeater-row data-existing="{{ $isExisting ? '1' : '0' }}" data-section-row>
    @if($isExisting)
        <input type="hidden" name="{{ $field }}[id]" value="{{ $sectionId }}">
    @endif
    <input type="hidden" name="{{ $field }}[_delete]" value="0" data-repeater-delete>
    <span class="repeater-row-drag" data-repeater-drag title="Drag to reorder" aria-hidden="true">
        <i class="bi bi-grip-vertical"></i>
    </span>
    <div class="repeater-inline-fields">
        <div class="flex-grow-1 min-w-0">
            <input type="text" class="form-control form-control-sm @error('sections.'.$sectionIndex.'.label') is-invalid @enderror"
                   name="{{ $field }}[label]" value="{{ $row['label'] ?? '' }}"
                   placeholder="e.g. Section 1: Engagement" aria-label="Section name">
            @error('sections.'.$sectionIndex.'.label')<div class="text-danger small">{{ $message }}</div>@enderror
            @error('sections.'.$sectionIndex.'.scored')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="form-check form-switch m-0 ps-0 d-flex align-items-center gap-2 flex-shrink-0">
            @if($isUsed)
                <input type="hidden" name="{{ $field }}[scored]" value="{{ $isScored ? 1 : 0 }}">
            @else
                <input type="hidden" name="{{ $field }}[scored]" value="0">
            @endif
            <input type="checkbox" class="form-check-input ms-0" role="switch" id="{{ $control }}-scored"
                   @unless($isUsed) name="{{ $field }}[scored]" @endunless value="1"
                   @checked($isScored) @disabled($isUsed) data-section-scored
                   @if($isUsed) aria-describedby="{{ $control }}-locked" @endif>
            <label class="form-check-label small text-nowrap" for="{{ $control }}-scored">Pass/Fail</label>
        </div>
    </div>
    <div class="repeater-row-actions">
        @if($isUsed)
            <span class="small text-muted text-nowrap" id="{{ $control }}-locked" title="Used by recorded submissions, so it can't be removed and its scoring is locked.">Used</span>
        @else
            <button type="button" class="repeater-icon-btn repeater-icon-btn-danger" data-repeater-remove title="Remove section" aria-label="Remove section">
                <i class="bi bi-x-circle" aria-hidden="true"></i>
            </button>
        @endif
    </div>
</div>
