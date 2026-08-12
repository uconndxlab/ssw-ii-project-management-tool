@php
    $agreement = $agreement ?? null;
    $normalizeKfs = fn ($value) => strtoupper(trim((string) $value));
    $selectedKfsNumbers = collect(old('kfs_numbers', $agreement?->kfsAccounts?->pluck('number')->all() ?? []))
        ->map($normalizeKfs)
        ->filter()
        ->unique()
        ->values()
        ->all();
@endphp

<div data-agreement-kfs-section data-selected-kfs='@json($selectedKfsNumbers)'>
    <label class="form-label">KFS Accounts</label>
    <div class="d-flex flex-wrap gap-2 mb-2" data-kfs-list></div>
    <div class="d-flex gap-2 align-items-start">
        <div class="form-control d-flex align-items-center py-1" style="min-height: 42px;">
            <input type="text"
                   class="border-0 flex-grow-1"
                   style="outline: none; min-width: 160px;"
                   data-kfs-input
                   placeholder="Enter a KFS number and press Add"
                   autocomplete="off">
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm px-3" style="min-width: 140px; height: 42px;" data-kfs-add-button>Add KFS</button>
    </div>
    <div class="form-text mt-2">Attach KFS accounts to this agreement. You can type any 1-7 character alphanumeric KFS number.</div>
    <div class="text-danger small mt-2 d-none" data-kfs-inline-error></div>
    <div data-kfs-hidden-inputs></div>

    @error('kfs_numbers')
        <div class="text-danger small mt-2">{{ $message }}</div>
    @enderror
    @error('kfs_numbers.*')
        <div class="text-danger small mt-2">{{ $message }}</div>
    @enderror
</div>

@once
<script>
(function () {
    function parseJson(text, fallback) {
        try {
            return JSON.parse(text || '');
        } catch (error) {
            return fallback;
        }
    }

    function normalizeKfs(value) {
        return String(value || '').trim().toUpperCase();
    }

    function isValidKfs(value) {
        return /^[A-Za-z0-9]{1,7}$/.test(value);
    }

    function initializeAgreementKfsSection(section) {
        if (section.dataset.agreementKfsInitialized === 'true') {
            return;
        }

        const addButton = section.querySelector('[data-kfs-add-button]');
        const input = section.querySelector('[data-kfs-input]');
        const list = section.querySelector('[data-kfs-list]');
        const hiddenInputs = section.querySelector('[data-kfs-hidden-inputs]');
        const inlineError = section.querySelector('[data-kfs-inline-error]');
        let selectedNumbers = parseJson(section.dataset.selectedKfs, []).map(normalizeKfs).filter(Boolean);

        function render() {
            list.innerHTML = '';
            hiddenInputs.innerHTML = '';

            selectedNumbers.forEach(function (number) {
                const badge = document.createElement('span');
                const removeButton = document.createElement('button');
                const hiddenInput = document.createElement('input');

                badge.className = 'badge text-bg-light border d-inline-flex align-items-center gap-1';
                badge.innerHTML = '<span class="d-inline-flex flex-wrap align-items-baseline gap-0"></span>';

                const text = badge.querySelector('span');
                text.textContent = number;

                removeButton.type = 'button';
                removeButton.className = 'btn-close';
                removeButton.style.fontSize = '10px';
                removeButton.setAttribute('aria-label', 'Remove KFS account');
                removeButton.addEventListener('click', function () {
                    selectedNumbers = selectedNumbers.filter(function (value) {
                        return value !== number;
                    });
                    render();
                });

                hiddenInput.type = 'hidden';
                hiddenInput.name = 'kfs_numbers[]';
                hiddenInput.value = number;

                badge.appendChild(text);
                badge.appendChild(removeButton);
                list.appendChild(badge);
                hiddenInputs.appendChild(hiddenInput);
            });

            section.dispatchEvent(new CustomEvent('agreement-kfs:change', {
                bubbles: true,
                detail: {
                    numbers: selectedNumbers.slice(),
                },
            }));
        }

        function showError(message) {
            if (!inlineError) {
                return;
            }

            inlineError.textContent = message;
            inlineError.classList.remove('d-none');
        }

        function clearError() {
            if (!inlineError) {
                return;
            }

            inlineError.textContent = '';
            inlineError.classList.add('d-none');
        }

        function tryAddCurrentValue() {
            const number = normalizeKfs(input.value);

            clearError();

            if (number === '') {
                return;
            }

            if (!isValidKfs(number)) {
                showError('KFS numbers must be 1-7 alphanumeric characters.');
                return;
            }

            if (!selectedNumbers.includes(number)) {
                selectedNumbers.push(number);
                selectedNumbers.sort();
            }

            input.value = '';
            render();
        }

        addButton?.addEventListener('click', tryAddCurrentValue);
        input?.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            tryAddCurrentValue();
        });

        render();
        section.dataset.agreementKfsInitialized = 'true';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-agreement-kfs-section]').forEach(function (section) {
            initializeAgreementKfsSection(section);
        });
    });
})();
</script>
@endonce
