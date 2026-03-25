<x-user-picker
    picker-id="activity-participants"
    name="participant_user_ids[]"
    :users="$users"
    :selected-ids="$selectedIds"
    search-placeholder="Search internal participants..."
    empty-message="No team members assigned to this agreement."
/>