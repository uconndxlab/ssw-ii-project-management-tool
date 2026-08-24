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

@once
    <style>
        .attachment-dropzone {
            cursor: pointer;
            border-style: dashed !important;
            border-color: var(--bs-primary) !important;
            border-radius: 0;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }

        .attachment-dropzone:hover,
        .attachment-dropzone:focus-visible,
        .attachment-dropzone.is-dragover {
            background-color: rgba(var(--bs-primary-rgb), 0.18) !important;
            border-color: var(--bs-primary) !important;
            box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.15);
            color: var(--bs-primary-text-emphasis) !important;
            outline: none;
        }
    </style>

    <script>
    (function () {
        document.addEventListener('DOMContentLoaded', function () {
            const attachmentInput = document.getElementById('attachment-input');
            const attachmentTableBody = document.getElementById('attachment-table-body');
            const attachmentDropzone = document.getElementById('attachment-dropzone');
            const attachmentHiddenInputs = document.getElementById('attachment-hidden-inputs');

            if (!attachmentInput || !attachmentTableBody || !attachmentDropzone || !attachmentHiddenInputs) return;

            const queuedAttachments = [];
            let nextQueuedAttachmentId = 1;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatBytes(bytes) {
                if (!bytes && bytes !== 0) return '—';

                const units = ['B', 'KB', 'MB', 'GB'];
                let value = Number(bytes);
                let unitIndex = 0;

                while (value >= 1024 && unitIndex < units.length - 1) {
                    value /= 1024;
                    unitIndex += 1;
                }

                return `${value.toFixed(value >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
            }

            function emptyStateRowMarkup() {
                return `
                    <tr class="attachment-empty-row">
                        <td colspan="3" class="text-center text-muted py-4 small">
                            Drop files below or click to browse attachments for this agreement.
                        </td>
                    </tr>
                `;
            }

            function hasVisibleAttachmentRows() {
                return Array.from(attachmentTableBody.querySelectorAll('[data-attachment-row]')).some(function (row) {
                    return window.getComputedStyle(row).display !== 'none';
                });
            }

            function queuedAttachmentRowMarkup(queuedAttachment) {
                return `
                    <tr data-attachment-row data-attachment-row-key="${escapeHtml(queuedAttachment.key)}" data-queued-attachment-row="true">
                        <td>
                            <div class="fw-semibold text-truncate" style="max-width: 100%;">
                                <i class="bi bi-file-earmark-plus me-1 text-info"></i>
                                ${escapeHtml(queuedAttachment.file.name)}
                            </div>
                        </td>
                        <td>${escapeHtml(formatBytes(queuedAttachment.file.size))}</td>
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
                `;
            }

            function syncFileInput() {
                const dataTransfer = new DataTransfer();

                queuedAttachments.forEach(function (queuedAttachment) {
                    dataTransfer.items.add(queuedAttachment.file);
                });

                attachmentInput.files = dataTransfer.files;
            }

            function addFiles(files) {
                Array.from(files || []).forEach(function (file) {
                    const key = 'queued-attachment-' + Date.now() + '-' + (nextQueuedAttachmentId++);
                    queuedAttachments.push({ key: key, file: file });

                    attachmentTableBody.insertAdjacentHTML('beforeend', queuedAttachmentRowMarkup({ key: key, file: file }));
                });

                syncFileInput();
                initTooltips(attachmentTableBody);
            }

            const form = attachmentInput.closest('form');
            if (form) {
                form.addEventListener('submit', function () {
                    syncFileInput();
                });
            }

            function removeQueuedAttachment(row) {
                const rowKey = row.dataset.attachmentRowKey;
                const index = queuedAttachments.findIndex(function (queuedAttachment) {
                    return queuedAttachment.key === rowKey;
                });

                if (index !== -1) {
                    queuedAttachments.splice(index, 1);
                }

                disposeTooltips(row);
                row.remove();
                syncFileInput();
            }

            function removeExistingAttachment(row) {
                const attachmentId = row.dataset.attachmentId;

                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'deleted_attachment_ids[]';
                hiddenInput.value = attachmentId;
                attachmentHiddenInputs.appendChild(hiddenInput);

                disposeTooltips(row);
                row.remove();
            }

            attachmentTableBody.addEventListener('click', function (event) {
                const removeButton = event.target.closest('[data-attachment-remove]');
                if (!removeButton) return;

                const row = removeButton.closest('[data-attachment-row]');
                if (!row) return;

                if (row.dataset.queuedAttachmentRow === 'true') {
                    removeQueuedAttachment(row);
                    return;
                }

                removeExistingAttachment(row);
            });

            attachmentDropzone.addEventListener('click', function () {
                attachmentInput.click();
            });

            attachmentDropzone.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    attachmentInput.click();
                }
            });

            attachmentDropzone.addEventListener('dragover', function (event) {
                event.preventDefault();
                attachmentDropzone.classList.add('is-dragover');
            });

            attachmentDropzone.addEventListener('dragleave', function () {
                attachmentDropzone.classList.remove('is-dragover');
            });

            attachmentDropzone.addEventListener('drop', function (event) {
                event.preventDefault();
                attachmentDropzone.classList.remove('is-dragover');
                addFiles(event.dataTransfer.files);
            });

            attachmentInput.addEventListener('change', function () {
                const selectedFiles = Array.from(attachmentInput.files || []);
                addFiles(selectedFiles);
            });

            initTooltips(document);
        });
    })();
    </script>
@endonce
