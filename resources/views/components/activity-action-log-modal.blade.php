<div class="modal fade" id="activity-action-log-modal" tabindex="-1" aria-labelledby="activity-action-log-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2" id="activity-action-log-modal-title">
                    Activity log
                    <span id="activity-action-log-modal-count" class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border d-none"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="activity-action-log-modal-body">
                    <p class="text-muted small mb-0">Loading…</p>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    <script>
        window.showActivityActionLogModal = function () {
            var body = document.getElementById('activity-action-log-modal-body');
            if (body) {
                body.innerHTML = '<p class="text-muted small mb-0">Loading…</p>';
            }

            var count = document.getElementById('activity-action-log-modal-count');
            if (count) {
                count.classList.add('d-none');
                count.textContent = '';
            }

            var modal = document.getElementById('activity-action-log-modal');
            if (modal && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        };
    </script>
@endonce
