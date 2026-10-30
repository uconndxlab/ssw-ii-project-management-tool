@php $groupField = 'requirement_groups['.$groupIndex.']'; $groupPrefix = 'requirement_groups.'.$groupIndex; @endphp
<section class="border rounded p-3 mt-3 cert-group" data-cert-group="{{ $groupIndex }}" @if(filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) hidden @endif>
    @if(filled($row['id'] ?? null))<input type="hidden" name="{{ $groupField }}[id]" value="{{ $row['id'] }}">@endif
    <input type="hidden" name="{{ $groupField }}[_delete]" value="{{ $row['_delete'] ?? 0 }}" data-cert-delete>
    <input type="hidden" name="{{ $groupField }}[phase]" value="{{ $phase }}" data-cert-phase>
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <h3 class="h6 mb-0" data-cert-group-summary>Requirement group</h3>
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-cert-move="up" aria-label="Move group up"><i class="bi bi-arrow-up" aria-hidden="true"></i></button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-cert-move="down" aria-label="Move group down"><i class="bi bi-arrow-down" aria-hidden="true"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-cert-remove="group">Ungroup</button>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-5"><x-form-field label="Group name" :for="'group-'.$groupIndex.'-label'" :name="$groupPrefix.'.label'"><input class="form-control" id="group-{{ $groupIndex }}-label" name="{{ $groupField }}[label]" value="{{ $row['label'] ?? '' }}" maxlength="255" data-cert-group-label></x-form-field></div>
        <div class="col-md-4"><x-form-field label="Requirements needed" :for="'group-'.$groupIndex.'-mode'" :name="$groupPrefix.'.satisfy_mode'"><select class="form-select" id="group-{{ $groupIndex }}-mode" name="{{ $groupField }}[satisfy_mode]" data-cert-group-mode><option value="all" @selected(($row['satisfy_mode'] ?? 'all') === 'all')>All in this group</option><option value="any" @selected(($row['satisfy_mode'] ?? '') === 'any')>Any one in this group</option><option value="n_of" @selected(($row['satisfy_mode'] ?? '') === 'n_of')>A set number in this group</option></select></x-form-field></div>
        <div class="col-md-3" data-cert-group-count><x-form-field label="Number needed" :for="'group-'.$groupIndex.'-count'" :name="$groupPrefix.'.required_count'"><input type="number" class="form-control" id="group-{{ $groupIndex }}-count" name="{{ $groupField }}[required_count]" value="{{ $row['required_count'] ?? '' }}" min="1" data-cert-required-count></x-form-field></div>
    </div>
    <div data-cert-members></div>
    <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-cert-add="requirement">Add requirement to group</button>
</section>
