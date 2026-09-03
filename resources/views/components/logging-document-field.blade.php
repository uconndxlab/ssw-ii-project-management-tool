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

@once
<style>
    .logging-document-dropzone {
        cursor: pointer;
        border-style: dashed !important;
        border-color: var(--bs-primary) !important;
        border-radius: 0;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
    }

    .logging-document-dropzone:hover,
    .logging-document-dropzone:focus-visible,
    .logging-document-dropzone.is-dragover {
        background-color: rgba(var(--bs-primary-rgb), 0.18) !important;
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.15);
        color: var(--bs-primary-text-emphasis) !important;
        outline: none;
    }
</style>
<script>
(function () {
    if (window.__loggingDocumentFieldInit) return;
    window.__loggingDocumentFieldInit = true;

    const rootOf = (el) => el?.closest('[data-logging-document]');

    function toggle(root, hasFile, fileName) {
        const name = root.querySelector('[data-logging-document-name]');
        const row = root.querySelector('[data-logging-document-file]');
        const drop = root.querySelector('[data-logging-document-dropzone-wrap]');
        const flag = root.querySelector('[data-logging-document-clear]');
        const input = root.querySelector('[data-logging-document-input]');

        if (hasFile) {
            if (name && fileName) {
                name.textContent = fileName;
                name.removeAttribute('href');
                name.removeAttribute('target');
            }
            row?.classList.remove('d-none');
            drop?.classList.add('d-none');
            if (flag) flag.value = '0';
            return;
        }

        row?.classList.add('d-none');
        drop?.classList.remove('d-none');
        if (input) input.value = '';
        if (flag) flag.value = '1';
    }

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-logging-document-name]')) return;

        const remove = event.target.closest('[data-logging-document-remove]');
        const zone = event.target.closest('[data-logging-document-dropzone]');
        const root = rootOf(remove || zone);
        if (!root) return;

        event.preventDefault();
        if (remove) {
            toggle(root, false);
            return;
        }
        root.querySelector('[data-logging-document-input]')?.click();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        const root = rootOf(event.target.closest('[data-logging-document-dropzone]'));
        if (!root) return;
        event.preventDefault();
        root.querySelector('[data-logging-document-input]')?.click();
    });

    document.addEventListener('dragover', function (event) {
        const zone = event.target.closest('[data-logging-document-dropzone]');
        if (!zone) return;
        event.preventDefault();
        zone.classList.add('is-dragover');
    });

    document.addEventListener('dragleave', function (event) {
        event.target.closest('[data-logging-document-dropzone]')?.classList.remove('is-dragover');
    });

    document.addEventListener('drop', function (event) {
        const zone = event.target.closest('[data-logging-document-dropzone]');
        const root = rootOf(zone);
        if (!root) return;

        event.preventDefault();
        zone.classList.remove('is-dragover');

        const file = event.dataTransfer?.files?.[0];
        const input = root.querySelector('[data-logging-document-input]');
        if (!file || !input) return;

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        toggle(root, true, file.name);
    });

    document.addEventListener('change', function (event) {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || !input.matches('[data-logging-document-input]')) return;
        const file = input.files?.[0];
        const root = rootOf(input);
        if (root && file) toggle(root, true, file.name);
    });
})();
</script>
@endonce
