@php
    $dimId = $row['id'] ?? null;
    $isExisting = filled($dimId);
    $options = $row['options'] ?? [];
@endphp
<div class="card mb-2" data-repeater-row data-existing="{{ $isExisting ? '1' : '0' }}">
    <div class="card-body py-2 px-3">
        @if($isExisting)
            <input type="hidden" name="dimensions[{{ $dimIndex }}][id]" value="{{ $dimId }}">
        @endif
        <input type="hidden" name="dimensions[{{ $dimIndex }}][_delete]" value="0" data-repeater-delete>
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label form-label-sm mb-1">Dimension name</label>
                <input type="text" class="form-control form-control-sm"
                       name="dimensions[{{ $dimIndex }}][name]" value="{{ $row['name'] ?? '' }}"
                       placeholder="e.g. Phase">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Sort</label>
                <input type="number" class="form-control form-control-sm"
                       name="dimensions[{{ $dimIndex }}][sort_order]" value="{{ $row['sort_order'] ?? 0 }}" min="0">
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove>
                    <i class="bi bi-trash"></i> Remove dimension
                </button>
            </div>
        </div>

        <div class="mt-2">
            <label class="form-label form-label-sm mb-1">Options</label>
            <x-repeater-rows
                name="dimensions[{{ $dimIndex }}][options]"
                :rows="$options"
                index-token="__OPT_INDEX__"
                add-label="Add option"
                :template="view('admin.certification-tools.partials.dimension-option-row', ['row' => [], 'dimIndex' => $dimIndex, 'optIndex' => '__OPT_INDEX__'])->render()"
            >
                @foreach($options as $optIndex => $option)
                    @include('admin.certification-tools.partials.dimension-option-row', ['row' => $option, 'dimIndex' => $dimIndex, 'optIndex' => $optIndex])
                @endforeach
            </x-repeater-rows>
        </div>
    </div>
</div>
