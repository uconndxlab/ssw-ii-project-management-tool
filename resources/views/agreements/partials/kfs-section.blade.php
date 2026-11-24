@php
    $agreement = $agreement ?? null;
    $normalizeKfs = fn ($value) => strtoupper(trim((string) $value));
    $selectedKfsNumbers = collect(old('kfs_numbers', $agreement?->kfsAccounts?->pluck('number')->all() ?? []))
        ->map($normalizeKfs)
        ->filter()
        ->unique()
        ->values()
        ->all();
    $kfsOptions = collect($kfsAccounts ?? collect())
        ->map(fn ($account) => [
            'value' => $normalizeKfs($account->number),
            'label' => $normalizeKfs($account->number),
            'search' => $normalizeKfs($account->number),
        ])
        ->unique('value')
        ->sortBy('label')
        ->values();
@endphp

<div data-agreement-kfs-section>
    <label class="form-label">KFS Accounts</label>
    <div class="d-flex flex-wrap gap-2 mb-2" data-kfs-list></div>
    <div class="d-flex gap-2 align-items-start flex-nowrap">
        <div class="flex-grow-1 min-w-0" data-kfs-picker-wrap>
            <x-token-picker
                picker-id="agreement-kfs-accounts"
                name="kfs_numbers[]"
                :options="$kfsOptions"
                :selected-ids="$selectedKfsNumbers"
                label-key="label"
                value-key="value"
                search-key="search"
                placeholder="Search or enter a KFS number…"
                :open-on-focus="true"
                :show-selected="false"
                :height="'220px'"
            />
        </div>
        <button type="button" class="btn btn-outline-primary flex-shrink-0" data-kfs-add-button>Add KFS</button>
    </div>
    <div class="form-text mt-2">1–7 character alphanumeric KFS numbers.</div>
    <div class="text-danger small mt-2 d-none" data-kfs-inline-error></div>

    @error('kfs_numbers')
        <div class="text-danger small mt-2">{{ $message }}</div>
    @enderror
    @error('kfs_numbers.*')
        <div class="text-danger small mt-2">{{ $message }}</div>
    @enderror
</div>
