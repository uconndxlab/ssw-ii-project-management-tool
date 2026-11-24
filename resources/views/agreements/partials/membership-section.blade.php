@php
    $agreement = $agreement ?? null;
    $selectedUserIds = old('user_ids', $agreement?->users?->pluck('id')->toArray() ?? []);
    $selectedTeamIds = old('team_ids', $agreement?->teams?->pluck('id')->toArray() ?? []);
    $selectedPrincipalInvestigatorIds = old('principal_investigator_ids', $agreement?->principalInvestigators?->pluck('id')->toArray() ?? []);

    $selectedTeamUserIds = collect($selectedTeamIds)
        ->flatMap(function ($teamId) use ($teams) {
            return $teams->firstWhere('id', (int) $teamId)?->users?->pluck('id') ?? [];
        })
        ->map(fn ($id) => (string) $id)
        ->unique()
        ->values()
        ->toArray();

    $selectedAdditionalUserIds = array_values(array_diff(
        array_map('strval', $selectedUserIds),
        $selectedTeamUserIds
    ));

    $selectedPrincipalInvestigatorIds = array_values(array_map('strval', $selectedPrincipalInvestigatorIds));

    $agreementUserOptions = $users->map(function ($user) {
        return [
            'value' => $user->id,
            'label' => $user->name,
            'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . $user->accessLabel()),
            'meta' => filled($user->po_number) ? 'PO: ' . $user->po_number : null,
            'contextLabels' => ['User'],
        ];
    });

    $membershipEntityBadgeClasses = [
        'team' => \App\Support\EntityBadge::relationClasses('team'),
        'role' => \App\Support\EntityBadge::categoryClasses('role'),
    ];
@endphp

<x-section-card title="Teams & Users">

        <div class="row g-5 align-items-stretch">
            <div class="col-lg-5 d-flex">
                <div class="d-flex flex-column w-100 h-100">
                    <div class="d-grid gap-4">
                        <x-form-field label="Teams" name="team_ids" help="Team members inherit access to this agreement." class="mb-0">
                            <x-token-picker
                                picker-id="agreement-{{ $agreement ? 'edit' : 'create' }}-teams"
                                name="team_ids[]"
                                :options="$teams->map(function ($team) {
                                    return [
                                        'value' => $team->id,
                                        'label' => $team->name,
                                        'search' => $team->name,
                                    ];
                                })"
                                :selected-ids="$selectedTeamIds"
                                label-key="label"
                                value-key="value"
                                search-key="search"
                                placeholder="Search to assign teams…"
                                disabled-placeholder="Select at least one program first…"
                                :disabled="empty($selectedProgramIds)"
                                :open-on-focus="false"
                                :show-selected="false"
                                :height="'220px'"
                                entity="team"
                            />
                        </x-form-field>

                        <x-form-field label="Additional users" name="user_ids" help="Users who are not covered by a selected team." class="mb-0">
                            <x-token-picker
                                picker-id="agreement-{{ $agreement ? 'edit' : 'create' }}-users"
                                name="user_ids[]"
                                :options="$agreementUserOptions"
                                :selected-ids="$selectedAdditionalUserIds"
                                label-key="label"
                                value-key="value"
                                search-key="search"
                                placeholder="Search additional users…"
                                disabled-placeholder="Select at least one program first…"
                                :disabled="empty($selectedProgramIds)"
                                :open-on-focus="false"
                                :show-selected="false"
                                :height="'220px'"
                                entity="user"
                            />
                        </x-form-field>
                    </div>

                    <div class="border rounded px-3 py-3 mt-3 mt-lg-auto{{ ($errors->has('membership') || $errors->has('principal_investigator_ids')) ? ' border-danger' : ' bg-body-tertiary' }}">
                        <div class="fw-semibold small mb-2">Membership requirements</div>

                        <div class="d-grid gap-2 small">
                            <div class="d-flex align-items-start gap-2" data-membership-requirement-members>
                                <i class="bi bi-x-circle-fill text-danger" data-membership-requirement-icon aria-hidden="true"></i>
                                <div>
                                    <div>Add at least one user or team to the agreement.</div>
                                </div>
                            </div>

                            <div class="d-flex align-items-start gap-2" data-membership-requirement-principal-investigators>
                                <i class="bi bi-x-circle-fill text-danger" data-membership-requirement-icon aria-hidden="true"></i>
                                <div>
                                    <div>Select at least one principal investigator.</div>
                                    <div class="text-muted">Use the PI toggle on a selected user in the roster.</div>
                                </div>
                            </div>
                        </div>

                        @error('membership')
                            <div class="text-danger small mt-3">{{ $message }}</div>
                        @enderror

                        @error('principal_investigator_ids')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="border rounded overflow-hidden d-flex flex-column h-100" style="min-height: 420px; background-color: var(--app-surface-fill);">
                    <div class="small text-muted px-3 py-2 border-bottom bg-body">
                        Selected users
                    </div>

                    <div class="flex-grow-1 overflow-auto" style="min-height: 0;">
                        <div class="m-3 mt-2 mb-2" data-agreement-membership-section
                             data-team-picker-id="agreement-{{ $agreement ? 'edit' : 'create' }}-teams"
                             data-user-picker-id="agreement-{{ $agreement ? 'edit' : 'create' }}-users"
                             data-all-user-ids='@json($agreementUserOptions->pluck("value")->values())'
                             data-program-allowed-user-ids='@json($agreementUserOptions->pluck("value")->values())'
                             data-selected-principal-investigator-ids='@json($selectedPrincipalInvestigatorIds)'
                             data-user-names='@json($users->mapWithKeys(fn ($user) => [(string) $user->id => $user->name]))'
                             data-user-po-numbers='@json($users->mapWithKeys(fn ($user) => [(string) $user->id => $user->po_number]))'
                             data-user-roles='@json($users->mapWithKeys(fn ($user) => [(string) $user->id => $user->accessLabel()]))'
                             data-entity-badge-classes='@json($membershipEntityBadgeClasses)'
                             data-team-labels='@json($teams->pluck("name", "id"))'
                             data-team-members='@json($teams->mapWithKeys(function ($team) {
                                 return [(string) $team->id => $team->users->pluck("id")->map(fn ($id) => (string) $id)->values()];
                             }))'>
                            <div data-principal-investigator-inputs></div>
                            <div class="d-grid gap-2" data-membership-ledger></div>

                            <div class="text-muted small py-3 d-none" data-membership-summary-empty>
                                No teams or additional users have been added yet.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</x-section-card>
