@props([
    'formId',
    'cancelUrl'   => null,
    'resolveCancel' => true,
    'saveLabel'   => 'Save',
    'lastSavedAt' => null,
])

@php
    $hasErrors  = isset($errors) && $errors->any();
    $initialState = $hasErrors ? 'error' : ($lastSavedAt ? 'saved' : 'idle');
    $savedTime    = $lastSavedAt
        ? \Carbon\Carbon::parse($lastSavedAt)->format('g:i A')
        : '';
    $resolvedBackTarget = $resolveCancel
        ? app(\App\Services\SessionBackTargetService::class)->resolve(request())
        : null;
    $cancelUrl = $resolveCancel ? ($resolvedBackTarget['url'] ?? null) : $cancelUrl;
@endphp

{{-- Sticky bottom save bar --}}
<div class="save-bar"
     id="save-bar"
     data-form-id="{{ $formId }}"
     data-initial="{{ $initialState }}"
     data-saved-time="{{ $savedTime }}">
    <div class="container d-flex align-items-center justify-content-between gap-3 py-2">
        <span id="save-bar-status" class="save-bar-status small" aria-live="polite"></span>
        <div class="d-flex gap-2 align-items-center">
            @if($cancelUrl)
                <a href="{{ $cancelUrl }}" class="btn btn-sm btn-outline-secondary" id="save-bar-cancel">Cancel</a>
            @endif
            <button type="button"
                    class="btn btn-sm btn-primary"
                    id="save-bar-btn"
                    data-label="{{ $saveLabel }}">
                {{ $saveLabel }}
            </button>
        </div>
    </div>
</div>

@once
<style>
    .save-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #fff;
        border-top: 1px solid #dee2e6;
        box-shadow: 0 -2px 12px rgba(0,0,0,.08);
        z-index: 1031;
        min-height: 54px;
    }
    body {
        padding-bottom: 70px;
    }
</style>
@endonce

@once
<script>
(function () {
    function initSaveBar() {
        const bar = document.getElementById('save-bar');
        if (!bar) return;

        const formId  = bar.dataset.formId;
        const form    = document.getElementById(formId);
        if (!form) return;

        const status  = document.getElementById('save-bar-status');
        const btn     = document.getElementById('save-bar-btn');
        const initial = bar.dataset.initial   || 'idle';
        const saved   = bar.dataset.savedTime || '';

        let dirty     = false;
        let submitted = false;

        const states = {
            idle:   { html: '',                                                              btnClass: 'btn-primary',         disabled: false },
            dirty:  { html: '<span class="text-warning fw-semibold">&#9679; Unsaved changes</span>',   btnClass: 'btn-primary',         disabled: false },
            saving: { html: '<span class="text-secondary">Saving&hellip;</span>',            btnClass: 'btn-secondary',       disabled: true  },
            saved:  { html: `<span class="text-success">&#10003; Saved &middot; ${saved}</span>`, btnClass: 'btn-outline-secondary', disabled: false },
            error:  { html: '<span class="text-danger">&#9888; Fix errors above to save</span>', btnClass: 'btn-primary',    disabled: false },
        };

        function setState(name) {
            const s = states[name] || states.idle;
            status.innerHTML = s.html;
            btn.disabled = s.disabled;
            // Swap button class
            ['btn-primary','btn-secondary','btn-outline-secondary'].forEach(c => btn.classList.remove(c));
            btn.classList.add(s.btnClass);
        }

        // Set initial state
        setState(initial);

        // Clicking the save bar button submits the form
        btn.addEventListener('click', function () {
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            setState('saving');
            submitted = true;
            form.submit();
        });

        // Track dirty state
        function markDirty() {
            if (!dirty) {
                dirty = true;
                if (initial !== 'error') setState('dirty');
            }
        }

        form.addEventListener('input',  markDirty);
        form.addEventListener('change', markDirty);

        // When form is actually submitted via submit event, mark saving
        form.addEventListener('submit', function () {
            setState('saving');
            dirty = false;
        });

        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', function (e) {
            if (dirty && !submitted) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSaveBar);
    } else {
        initSaveBar();
    }
})();
</script>
@endonce
