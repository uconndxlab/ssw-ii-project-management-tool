@php
    $row = $row ?? [];
    $fieldPrefix = $fieldPrefix ?? 'deliverable_editor';
    $selectedUserIds = $row['user_ids'] ?? [];
@endphp

<div class="deliverable-editor-fields" data-deliverable-editor-fields>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">Contact Family</label>
            <select class="form-select" name="{{ $fieldPrefix }}[contact_family_id]" data-deliverable-contact-family>
                <option value="">Select contact family...</option>
                @foreach($contactFamilies as $family)
                    <option value="{{ $family->id }}" @selected((string) ($row['contact_family_id'] ?? '') === (string) $family->id)>
                        {{ $family->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Activity Type</label>
            <select class="form-select" name="{{ $fieldPrefix }}[activity_type_id]" data-deliverable-activity-type>
                <option value="">Select activity type...</option>
                @foreach($activityTypes as $type)
                    <option value="{{ $type->id }}"
                            data-contact-family-id="{{ $type->contact_family_id }}"
                            @selected((string) ($row['activity_type_id'] ?? '') === (string) $type->id)>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <label class="form-label">Required Hours</label>
            <input type="number"
                   class="form-control"
                   name="{{ $fieldPrefix }}[required_hours]"
                   value="{{ $row['required_hours'] ?? '' }}"
                   min="0"
                   step="0.1">
        </div>
        <div class="col-sm-6">
            <label class="form-label">Required Activities</label>
            <input type="number"
                   class="form-control"
                   name="{{ $fieldPrefix }}[required_activities]"
                   value="{{ $row['required_activities'] ?? '' }}"
                   min="0"
                   step="1">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Notes</label>
        <textarea class="form-control"
                  name="{{ $fieldPrefix }}[notes]"
                  rows="3"
                  maxlength="500">{{ $row['notes'] ?? '' }}</textarea>
    </div>

    <div class="mb-0">
        <label class="form-label">Assigned Users</label>
        @if($users->isEmpty())
            <p class="text-muted small mb-0">No users available.</p>
        @else
            <div class="border rounded p-2" style="max-height:180px;overflow-y:auto;">
                @foreach($users as $user)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               name="{{ $fieldPrefix }}[user_ids][]"
                               value="{{ $user->id }}"
                               id="{{ $fieldPrefix }}_user_{{ $user->id }}"
                               {{ in_array($user->id, $selectedUserIds) ? 'checked' : '' }}>
                        <label class="form-check-label" for="{{ $fieldPrefix }}_user_{{ $user->id }}">
                            {{ $user->name }}
                            <span class="text-muted small">{{ ucfirst($user->role) }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>