@php
    $agreement = $agreement ?? null;

    $deletedAttachmentIds = collect(old('deleted_attachment_ids', []))
        ->filter()
        ->map(fn ($id) => (string) $id)
        ->values()
        ->all();

    $existingAttachments = $agreement?->attachments?->reject(function ($attachment) use ($deletedAttachmentIds) {
        return in_array((string) $attachment->id, $deletedAttachmentIds, true);
    }) ?? collect();
@endphp

<x-section-card title="Attachments">

        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Size</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="attachment-table-body">
                    @foreach($existingAttachments as $attachment)
                        @php
                            $attachmentRowKey = 'attachment-' . $attachment->id;
                        @endphp
                        <tr data-attachment-row data-attachment-row-key="{{ $attachmentRowKey }}" data-attachment-id="{{ $attachment->id }}">
                            <td>
                                <div class="fw-semibold text-truncate" style="max-width: 100%;">
                                    <i class="bi bi-file-earmark me-1 text-info"></i>
                                    <a href="{{ $attachment->download_url }}" target="_blank" class="text-decoration-none">{{ $attachment->filename }}</a>
                                </div>
                            </td>
                            <td>{{ $attachment->formatted_size }}</td>
                            <td class="text-end text-nowrap">
                                <button type="button"
                                        class="btn btn-outline-danger btn-sm"
                                        data-attachment-remove
                                        data-bs-toggle="tooltip"
                                        data-bs-title="Remove attachment"
                                        aria-label="Remove attachment">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="p-3 pt-2">
                            <div id="attachment-dropzone"
                                   class="attachment-dropzone px-3 py-4 text-center border border-2 border-primary bg-primary bg-opacity-10 text-primary-emphasis"
                                 role="button"
                                 tabindex="0"
                                 aria-label="Upload attachments by dropping files or browsing">
                                <div class="fw-semibold text-primary">Drop files here or click to browse</div>
                                <div class="small text-primary">PDF, Word, Excel, or text files. Max {{ (int) round(config('uploads.max_file_kb') / 1024) }}MB each.</div>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <input type="file"
               class="d-none"
               id="attachment-input"
               name="attachments[]"
               multiple
               accept=".pdf,.doc,.docx,.xls,.xlsx,.txt">

        <div id="attachment-hidden-inputs">
            @foreach($deletedAttachmentIds as $attachmentId)
                <input type="hidden" name="deleted_attachment_ids[]" value="{{ $attachmentId }}">
            @endforeach
        </div>
</x-section-card>
