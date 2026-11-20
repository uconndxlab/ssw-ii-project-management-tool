<div data-repeater-row data-history-row class="border-bottom py-3">
    @if(filled($row['id'] ?? null))<input type="hidden" name="rows[{{ $index }}][id]" value="{{ $row['id'] }}">@endif
    <div class="row">
        <div class="col-md-4">
            <x-form-field label="Award date" :for="'award-date-'.$index" :name="'rows.'.$index.'.awarded_at'" required>
                <input class="form-control" type="date" id="award-date-{{ $index }}" name="rows[{{ $index }}][awarded_at]" value="{{ $row['awarded_at'] ?? '' }}" max="{{ today()->toDateString() }}" required data-award-date>
            </x-form-field>
        </div>
        <div class="col-md-4">
            @if($enrollment->certificate->validity_months !== null)
                <x-date-override-field :name="'rows['.$index.'][expires_at]'" :id="'expiry-'.$index" label="Expiry" :value="$row['expires_at'] ?? ''" :overridden="(bool) ($row['expiry_overridden'] ?? false)" :override-name="'rows['.$index.'][expiry_overridden]'" />
            @else
                <input type="hidden" name="rows[{{ $index }}][expiry_overridden]" value="0">
                <p class="small text-muted">No expiry</p>
            @endif
        </div>
        <div class="col-md-4">
            <x-form-field label="Originally awarded by" :for="'original-awarder-'.$index" :name="'rows.'.$index.'.originally_awarded_by'">
                <input class="form-control" id="original-awarder-{{ $index }}" name="rows[{{ $index }}][originally_awarded_by]" maxlength="255" value="{{ $row['originally_awarded_by'] ?? '' }}">
            </x-form-field>
        </div>
    </div>
    @if(! filled($row['id'] ?? null))<button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove>Remove unsaved award</button>@else<p class="small text-muted mb-0">Saved award · cycle {{ is_numeric($index) ? $index + 1 : "" }} · cannot be removed</p>@endif
</div>
