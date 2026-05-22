<form hx-patch="{{ route('agreements.update-deliverable', [$agreement, $deliverable]) }}"
      hx-target="#deliverable-list"
      hx-swap="innerHTML">

    <div class="mb-3">
        <label class="form-label">Contact Family</label>
        <select class="form-select"
                name="contact_family_id"
                hx-get="{{ route('activity-types.by-family') }}"
                hx-target="#edit-deliverable-at-{{ $deliverable->id }}"
                hx-swap="innerHTML"
                hx-include="this">
            <option value="">— Any —</option>
            @foreach($contactFamilies as $family)
                <option value="{{ $family->id }}" @selected((string)$deliverable->contact_family_id === (string)$family->id)>
                    {{ $family->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Activity Type</label>
        <select class="form-select"
                name="activity_type_id"
                id="edit-deliverable-at-{{ $deliverable->id }}">
            <option value="">— Any —</option>
            @foreach($activityTypes as $type)
                <option value="{{ $type->id }}" @selected((string)$deliverable->activity_type_id === (string)$type->id)>
                    {{ $type->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <label class="form-label">Required Hours</label>
            <input type="number"
                   class="form-control"
                   name="required_hours"
                   value="{{ $deliverable->required_hours }}"
                   min="0" step="0.1">
        </div>
        <div class="col-sm-6">
            <label class="form-label">Required Activities</label>
            <input type="number"
                   class="form-control"
                   name="required_activities"
                   value="{{ $deliverable->required_activities }}"
                   min="0" step="1">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Notes</label>
        <textarea class="form-control"
                  name="notes"
                  rows="3"
                  maxlength="500">{{ $deliverable->notes }}</textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Assigned Users</label>
        @if($users->isEmpty())
            <p class="text-muted small mb-0">No users available.</p>
        @else
            <div class="border rounded p-2" style="max-height:180px;overflow-y:auto;">
                @foreach($users as $user)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               name="user_ids[]"
                               value="{{ $user->id }}"
                               id="edit_del_user_{{ $deliverable->id }}_{{ $user->id }}"
                               {{ in_array($user->id, $assignedUserIds) ? 'checked' : '' }}>
                        <label class="form-check-label" for="edit_del_user_{{ $deliverable->id }}_{{ $user->id }}">
                            {{ $user->name }}
                            <span class="text-muted small">{{ ucfirst($user->role) }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>

</form>
