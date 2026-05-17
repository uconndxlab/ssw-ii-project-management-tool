@foreach($programs as $program)
    <div class="form-check mb-2">
        <input class="form-check-input" 
               type="checkbox" 
               name="program_ids[]" 
               id="program_{{ $program->id }}" 
               value="{{ $program->id }}"
               @if($agreement->programs->contains($program->id)) checked @endif>
        <label class="form-check-label" for="program_{{ $program->id }}">
            {{ $program->name }}
        </label>
    </div>
@endforeach

@if($programs->isEmpty())
    <p class="text-muted small">No programs available for this project</p>
@endif
