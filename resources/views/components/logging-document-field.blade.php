@props([
    'id',
    'name',
    'label',
    'activity' => null,
    'field',
    'context' => 'agreement',
    'agreementId' => null,
    'value' => null,
])

@php
    $contextId = match ($context) {
        'agreement' => $agreementId,
        'activity_type' => $activity?->activity_type_id,
        'sub_activity' => $activity?->sub_activity_id,
        default => $activity?->activityType?->contact_family_id,
    };
    $contextId = $contextId !== null ? (int) $contextId : null;
    $fileName = $activity?->loggingFieldFileName($context, (int) $field->id, $contextId)
        ?: (filled($value) ? basename((string) $value) : null);
    $downloadUrl = $activity?->loggingFieldDocumentUrl($context, (int) $field->id, $contextId);
    $hasFile = filled($fileName);
    $clearName = str_replace('_logging_values', '_logging_cleared', $name);
    $rules = 'PDF, Word, Excel, or image files. Max '.(int) round(config('uploads.max_file_kb') / 1024).'MB.';
@endphp

<div data-logging-document>
    <div class="form-control d-flex align-items-center gap-2 min-w-0 {{ $hasFile ? '' : 'd-none' }}" data-logging-document-file>
        <a class="text-primary text-truncate min-w-0 flex-grow-1"
           @if($downloadUrl) href="{{ $downloadUrl }}" target="_blank" @endif
           data-logging-document-name>{{ $fileName }}</a>
        <button type="button"
                class="btn btn-sm btn-link text-danger text-decoration-none p-0 lh-1 fs-4 flex-shrink-0"
                data-logging-document-remove
                data-bs-toggle="tooltip"
                data-bs-title="Remove attachment"
                aria-label="Remove attachment">&times;</button>
    </div>

    <div class="{{ $hasFile ? 'd-none' : '' }}" data-logging-document-dropzone-wrap>
        <div class="logging-document-dropzone px-3 py-2 text-center border border-2 border-primary bg-primary bg-opacity-10 text-primary-emphasis"
             data-logging-document-dropzone
             role="button"
             tabindex="0"
             aria-label="Upload {{ $label }} by dropping a file or browsing">
            <div class="fw-semibold text-primary">Drop file here or click to browse</div>
            <div class="small text-primary">{{ $rules }}</div>
        </div>
    </div>

    <input type="hidden" name="{{ $clearName }}" value="0" data-logging-document-clear>
    <input type="file"
           class="d-none"
           id="{{ $id }}"
           name="{{ $name }}"
           accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
           data-logging-document-input>
</div>
