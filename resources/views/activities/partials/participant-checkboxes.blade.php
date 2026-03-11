@foreach($users as $user)
<div class="form-check">
    <input class="form-check-input" 
           type="checkbox" 
           name="participant_user_ids[]" 
           value="{{ $user->id }}" 
           id="participant_{{ $user->id }}"
           {{ in_array($user->id, $selectedIds) ? 'checked' : '' }}>
    <label class="form-check-label" for="participant_{{ $user->id }}">
        {{ $user->name }}
    </label>
</div>
@endforeach
