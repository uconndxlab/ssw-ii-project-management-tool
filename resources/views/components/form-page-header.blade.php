@props([
    'entityType' => null,
    'entityTypeBadgeClass' => 'bg-secondary',
    'mode' => 'create',
    'recordName' => null,
    'title' => null,
    'showActive' => false,
    'activeDefault' => true,
    'activeHelp' => null,
    'activeInputId' => 'active',
    'backRoute' => null,
    'backLabel' => null,
])

@php
    $isSimple = filled($title);
    $isEdit = ! $isSimple && $mode === 'edit' && filled($recordName);
@endphp

<div {{ $attributes->merge(['class' => 'mb-4']) }}>
    @if(filled($backRoute) && filled($backLabel))
        <a href="{{ $backRoute }}" class="btn btn-link btn-sm text-muted text-decoration-none px-0 mb-2">
            <i class="bi bi-arrow-left me-1"></i>{{ $backLabel }}
        </a>
    @endif

    @if($isSimple)
        <h1 class="h2 mb-0 fw-bold">{{ $title }}</h1>
    @else
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <x-entity-type-badge :label="$entityType" :badge-class="$entityTypeBadgeClass" />
                    @if($isEdit)
                        <span class="badge bg-secondary rounded-pill text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">
                            Editing
                        </span>
                    @endif
                </div>

                @if($isEdit)
                    <h1 class="h2 mb-1 fw-bold">Edit {{ $entityType }}</h1>
                    <p class="mb-0 text-muted">
                        Updating <span class="fw-semibold text-body">{{ $recordName }}</span>
                    </p>
                @else
                    <h1 class="h2 mb-0 fw-bold">Create {{ $entityType }}</h1>
                @endif
            </div>

            @if($showActive)
                <div class="card shadow-sm flex-shrink-0 ms-sm-auto" style="min-width: 14rem; max-width: 18rem;">
                    <div class="card-body py-2 px-3">
                        <input type="hidden" name="active" value="0">
                        <div class="form-check form-switch m-0 ps-0 d-flex align-items-center gap-2">
                            <input type="checkbox"
                                   class="form-check-input ms-0 @error('active') is-invalid @enderror"
                                   role="switch"
                                   id="{{ $activeInputId }}"
                                   name="active"
                                   value="1"
                                   {{ filter_var($activeDefault, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                            <label class="form-check-label" for="{{ $activeInputId }}">Active</label>
                        </div>
                        @if($activeHelp)
                            <div class="form-text mt-1 mb-0">{{ $activeHelp }}</div>
                        @endif
                        @error('active')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
