@if(isset($users) && $users->isNotEmpty())
    <x-user-picker
        picker-id="{{ $pickerId }}"
        name="participant_user_ids[]"
        :users="$users"
        :selected-ids="$selectedIds"
        search-placeholder="Search users..."
        empty-message="No users available."
        height="300px"
        :show-role="false"
    />
@else
    <small class="text-muted">Select an agreement first to see team members</small>
@endif