<div class="border-bottom mb-3 pb-2" data-cert-role-row="{{ $roleIndex }}" @if(filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) hidden @endif>
    @if(filled($row['id'] ?? null))<input type="hidden" name="roles[{{ $roleIndex }}][id]" value="{{ $row['id'] }}" data-cert-saved-role>@endif
    <input type="hidden" name="roles[{{ $roleIndex }}][_delete]" value="{{ $row['_delete'] ?? 0 }}" data-cert-delete>
    <x-form-field label="Role name" :for="'role-'.$roleIndex.'-name'" :name="'roles.'.$roleIndex.'.name'"><input class="form-control" id="role-{{ $roleIndex }}-name" name="roles[{{ $roleIndex }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="255" data-cert-role-name></x-form-field>
    <input type="hidden" name="roles[{{ $roleIndex }}][active]" value="0">
    <div class="form-check"><input type="checkbox" class="form-check-input" id="role-{{ $roleIndex }}-active" name="roles[{{ $roleIndex }}][active]" value="1" @checked(filter_var($row['active'] ?? true, FILTER_VALIDATE_BOOLEAN))><label class="form-check-label" for="role-{{ $roleIndex }}-active">Active</label></div>
    <button type="button" class="btn btn-sm btn-link text-danger px-0" data-cert-remove="role">Remove role</button>
</div>
