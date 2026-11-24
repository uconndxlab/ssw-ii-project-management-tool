@php
    $agreement = $agreement ?? null;
    $selectedStateIds = $selectedStateIds ?? [];
    $selectedOrganizationIds = old('organization_ids', $agreement?->organizations?->pluck('id')->toArray() ?? []);
    $organizationKfsErrorMessage = collect($errors->messages())
        ->filter(fn ($messages, $key) => str_starts_with($key, 'organization_kfs_numbers'))
        ->flatten()
        ->first();

    $selectedPayorSourceIds = old(
        'organization_payor_source_ids',
        $agreement?->organizations
            ?->filter(fn ($organization) => (bool) $organization->pivot->payor_source)
            ->pluck('id')
            ->toArray() ?? []
    );

    $selectedRecipientIds = old(
        'organization_recipient_ids',
        $agreement?->organizations
            ?->filter(fn ($organization) => (bool) $organization->pivot->recipient)
            ->pluck('id')
            ->toArray() ?? []
    );

    $selectedOrganizationKfsNumbers = old(
        'organization_kfs_numbers',
        $agreement?->organizationKfsAccounts
            ?->groupBy(fn ($account) => (string) $account->pivot->organization_id)
            ->map(fn ($accounts) => $accounts->pluck('number')->values()->all())
            ->all() ?? []
    );

    $organizationOptions = $organizations->map(function ($organization) {
        return [
            'value' => $organization->id,
            'label' => $organization->name,
            'search' => trim($organization->name . ' ' . ($organization->po_number ?? '')),
            'meta' => filled($organization->po_number) ? 'PO: ' . $organization->po_number : null,
        ];
    });

    $organizationLabels = $organizations->pluck('name', 'id');
    $organizationPoNumbers = $organizations->mapWithKeys(fn ($organization) => [
        (string) $organization->id => $organization->po_number,
    ]);
    $organizationEntityBadgeClass = \App\Support\EntityBadge::relationClasses('organization');
@endphp

<div data-agreement-organizations-wrap>
    <div class="row g-4 align-items-start mb-4">
        <div class="col-lg-6">
            <x-form-field label="Organizations" name="organization_ids" help="Must match a selected program and state.">
                <x-token-picker
                    picker-id="agreement-organizations"
                    name="organization_ids[]"
                    :options="$organizationOptions"
                    :selected-ids="$selectedOrganizationIds"
                    label-key="label"
                    value-key="value"
                    search-key="search"
                    placeholder="Search organizations…"
                    disabled-placeholder="Select at least one program and state first…"
                    :disabled="empty($selectedProgramIds) || empty($selectedStateIds)"
                    :open-on-focus="false"
                    :show-selected="true"
                    :height="'220px'"
                    entity="organization"
                />
            </x-form-field>

            @error('organization_payor_source_ids')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
            @error('organization_recipient_ids')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
            @if($organizationKfsErrorMessage)
                <div class="text-danger small mt-1">{{ $organizationKfsErrorMessage }}</div>
            @endif
        </div>

        <div class="col-lg-6">
            @include('agreements.partials.kfs-section', [
                'agreement' => $agreement,
                'kfsAccounts' => $kfsAccounts ?? collect(),
            ])
        </div>
    </div>

    <div class="border rounded overflow-hidden d-flex flex-column" style="background-color: var(--app-surface-fill);">
        <div class="small text-muted px-3 py-2 border-bottom bg-body">
            Selected organizations
        </div>

        <div class="flex-grow-1 overflow-auto" style="min-height: 0;">
            <div class="m-3 mt-2 mb-2" data-agreement-organizations-section
                 data-organization-picker-id="agreement-organizations"
                 data-selected-payor-source-ids='@json(array_values(array_map("strval", $selectedPayorSourceIds)))'
                 data-selected-recipient-ids='@json(array_values(array_map("strval", $selectedRecipientIds)))'
                 data-organization-labels='@json($organizationLabels)'
                 data-organization-po-numbers='@json($organizationPoNumbers)'
                 data-organization-badge-class="{{ $organizationEntityBadgeClass }}"
                 data-selected-organization-kfs-numbers='@json($selectedOrganizationKfsNumbers)'>
                <div data-organization-payor-source-inputs></div>
                <div data-organization-recipient-inputs></div>
                <div class="d-grid gap-2" data-organizations-ledger></div>

                <div class="text-muted small py-3 d-none" data-organizations-summary-empty>
                    No organizations have been added yet.
                </div>
            </div>
        </div>
    </div>
</div>
