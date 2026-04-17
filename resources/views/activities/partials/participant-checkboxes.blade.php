@if($agreement && $agreement->users->isNotEmpty())
    <x-user-picker
        picker-id="{{ $pickerId }}"
        name="participant_user_ids[]"
        :users="$agreement->users"
        :selected-ids="$selectedIds"
        search-placeholder="Search users..."
        empty-message="No users available."
        height="300px"
        :show-role="false"
    />
@elseif($agreement)
    <small class="text-muted">No team members assigned to this agreement</small>
@else
    <small class="text-muted">Select an agreement first to see team members</small>
@endif