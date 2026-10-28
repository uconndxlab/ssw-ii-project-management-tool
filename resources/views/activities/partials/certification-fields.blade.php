@php
    $certificationRows = old('certification_candidates', $certificationForm['saved'] ?? []);
    $candidateOptions = collect($certificationForm['candidates'] ?? [])->map(fn ($person) => [
        'id' => $person['id'], 'name' => $person['name'], 'search' => $person['name'].' '.$person['email'],
        'contextLabels' => array_column($person['agreements'], 'name'),
    ]);
    $certificationConfig = $certificationForm + ['candidates' => [], 'saved' => [], 'tools' => [], 'coach_program_ids' => []];
    $certificationConfig['review_requested'] = $activity?->certification_review_requested_at !== null;
    $certificationConfig['rows'] = $certificationRows;
    $certificationConfig['errors'] = collect($errors->messages())->filter(fn ($messages, $key) => str_starts_with($key, 'certification_candidates.'))->all();
@endphp
<x-section-card title="Certification" id="activity-certification" data-certification-editor>
    <script type="application/json" data-certification-config>{!! json_encode($certificationConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap" data-certification-empty>
        <span class="text-muted small">Tag candidates when this activity contributes to certification.</span>
        <button type="button" class="btn btn-sm btn-outline-primary" data-certification-add><i class="bi bi-plus-lg" aria-hidden="true"></i> Add candidates</button>
    </div>
    <div class="mt-3" data-certification-picker hidden>
        <x-form-field label="Candidates" for="activity-certification-picker-search" help="Choose from the selected agreements’ rosters. A person is tagged once, even across agreements.">
            <x-token-picker picker-id="activity-certification-picker" name="certification_candidate_choices[]"
                :options="$candidateOptions" search-key="search" :selected-ids="[]" :show-selected="false" :single="true"
                placeholder="Search candidates by name or email…" />
        </x-form-field>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap mt-3 mb-2" data-certification-bulk hidden>
        <label for="certification-bulk-role" class="small">Set role for all</label>
        <select id="certification-bulk-role" class="form-select form-select-sm w-auto" data-certification-bulk-role><option value="">Choose role…</option></select>
        <button type="button" class="btn btn-sm btn-outline-primary" data-certification-apply-role>Apply to eligible candidates</button>
    </div>
    <div class="mt-2" data-certification-rows></div>
    <div class="visually-hidden" role="status" aria-live="polite" data-certification-status></div>
    <x-form-options class="mt-3">
        <x-form-switch name="needs_certification_review" label="Needs certification review"
            :checked="old('needs_certification_review', $activity?->certification_review_requested_at !== null)"
            help="A coach with activity edit access reviews the candidates and records what they can." />
        <p class="form-text mb-0" data-certification-review-help hidden>A coach in an activity program must clear this flag.</p>
    </x-form-options>
    <noscript><p class="text-danger">Enable JavaScript to edit certification evidence. Existing evidence is preserved when you save other activity fields.</p></noscript>
</x-section-card>
