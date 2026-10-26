@php
    $prefix = 'requirements.'.$reqIndex;
    $field = 'requirements['.$reqIndex.']';
    $kind = $row['kind'] ?? '';
    $deleted = filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);
@endphp
<article class="cert-requirement border rounded mb-2" id="cert-req-{{ $reqIndex }}" data-cert-requirement="{{ $reqIndex }}" @if($deleted) hidden @endif>
    @if(filled($row['id'] ?? null))<input type="hidden" name="{{ $field }}[id]" value="{{ $row['id'] }}">@endif
    <input type="hidden" name="{{ $field }}[_delete]" value="{{ $deleted ? 1 : 0 }}" data-cert-delete>
    <input type="hidden" name="{{ $field }}[phase]" value="{{ $phase }}" data-cert-phase>
    <div class="d-flex align-items-start gap-2 p-3">
        <button type="button" class="btn text-start flex-grow-1 p-0 border-0" data-cert-edit aria-controls="cert-editor-{{ $reqIndex }}" aria-expanded="false">
            <strong class="d-block" data-cert-title>{{ $row['label'] ?? 'New requirement' }}</strong>
            <span class="d-block small text-muted mt-1" data-cert-summary></span>
            <span class="d-inline-block small text-primary mt-1">Edit requirement</span>
        </button>
        <div class="d-flex flex-wrap gap-1 cert-row-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-cert-move="up" aria-label="Move requirement up">↑</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-cert-move="down" aria-label="Move requirement down">↓</button>
            <button type="button" class="btn btn-sm btn-link text-danger" data-cert-remove="requirement">Remove</button>
        </div>
    </div>
    <div class="border-top p-3" id="cert-editor-{{ $reqIndex }}" data-cert-editor hidden>
        <x-form-field label="Work to count" :for="'req-'.$reqIndex.'-kind'" :name="$prefix.'.kind'">
            <select class="form-select" id="req-{{ $reqIndex }}-kind" name="{{ $field }}[kind]" data-cert-kind>
                <option value="">Choose requirement type…</option>
                @foreach(['activity_count' => 'Activity work', 'tool_submission' => 'Tool submissions', 'attestation' => 'Coach confirmation'] as $value => $label)
                    <option value="{{ $value }}" @selected($kind === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-form-field>
        <div data-cert-kind-details @if($kind === '') hidden @endif>
            <x-form-field label="Requirement name" :for="'req-'.$reqIndex.'-label'" :name="$prefix.'.label'" help="Give this requirement a short, recognizable name.">
                <input class="form-control" id="req-{{ $reqIndex }}-label" name="{{ $field }}[label]" value="{{ $row['label'] ?? '' }}" maxlength="255" data-cert-label>
            </x-form-field>
            <div data-cert-for="activity_count tool_submission">
                <div class="row g-3">
                    <div class="col-md-4">
                        <x-form-field label="Target" :for="'req-'.$reqIndex.'-target'" :name="$prefix.'.target_count'">
                            <input type="number" class="form-control" id="req-{{ $reqIndex }}-target" name="{{ $field }}[target_count]" value="{{ $row['target_count'] ?? 1 }}" min="1" data-cert-target>
                            <div class="form-text" data-cert-target-help></div>
                        </x-form-field>
                    </div>
                    <div class="col-md-8">
                        <x-form-field label="Role filter" :for="'req-'.$reqIndex.'-role'" :name="$prefix.'.certification_role_id'">
                            <select class="form-select" id="req-{{ $reqIndex }}-role" data-cert-role data-selected-role="{{ filled($row['role_index'] ?? null) ? 'new:'.$row['role_index'] : ($row['certification_role_id'] ?? '') }}">
                                <option value="">Any role</option>
                                @foreach($globalRoles as $role)<option value="{{ $role->id }}">{{ $role->name }}{{ !$role->active ? ' (inactive)' : '' }}</option>@endforeach
                            </select>
                            <input type="hidden" name="{{ $field }}[certification_role_id]" value="{{ $row['certification_role_id'] ?? '' }}" data-cert-role-id>
                            <input type="hidden" name="{{ $field }}[role_index]" value="{{ $row['role_index'] ?? '' }}" data-cert-role-index>
                            @error($prefix.'.role_index')<div class="text-danger small" data-field-error id="req-{{ $reqIndex }}-role-index-error">{{ $message }}</div>@enderror
                        </x-form-field>
                        <button type="button" class="btn btn-sm btn-link px-0 mb-3" data-cert-manage-roles>Add or edit certificate roles</button>
                    </div>
                </div>
            </div>
            <div data-cert-for="activity_count tool_submission">
                <div class="row g-3">
                    <div class="col-md-6">
                        <x-form-field label="Activity family" :for="'req-'.$reqIndex.'-family'" :name="$prefix.'.contact_family_id'">
                            <select class="form-select" id="req-{{ $reqIndex }}-family" name="{{ $field }}[contact_family_id]" data-cert-family>
                                <option value="">Any family</option>
                                @foreach($contactFamilies as $family)<option value="{{ $family->id }}" @selected((string)($row['contact_family_id'] ?? '') === (string)$family->id)>{{ $family->name }}</option>@endforeach
                            </select>
                        </x-form-field>
                    </div>
                    <div class="col-md-6">
                        <x-form-field label="Activity type" :for="'req-'.$reqIndex.'-type'" :name="$prefix.'.activity_type_id'">
                            <select class="form-select" id="req-{{ $reqIndex }}-type" name="{{ $field }}[activity_type_id]" data-cert-type>
                                <option value="">Any activity type</option>
                                @foreach($activityTypes as $type)<option value="{{ $type->id }}" @selected((string)($row['activity_type_id'] ?? '') === (string)$type->id)>{{ $type->name }}</option>@endforeach
                            </select>
                        </x-form-field>
                    </div>
                </div>
                <x-form-field label="Work lookback override (months)" :for="'req-'.$reqIndex.'-window'" :name="$prefix.'.window_months'" help="Blank inherits the certificate default. This controls eligible work, not award validity.">
                    <input type="number" class="form-control" id="req-{{ $reqIndex }}-window" name="{{ $field }}[window_months]" value="{{ $row['window_months'] ?? '' }}" min="1" data-cert-window>
                    <div class="form-text" data-cert-effective-window></div>
                </x-form-field>
            </div>
            <div data-cert-for="tool_submission">
                <x-form-field label="Submission tool" :for="'req-'.$reqIndex.'-tool'" :name="$prefix.'.certification_tool_id'">
                    <select class="form-select" id="req-{{ $reqIndex }}-tool" name="{{ $field }}[certification_tool_id]" data-cert-tool>
                        <option value="">Select tool…</option>
                        @foreach($certificationTools as $tool)
                            <option value="{{ $tool->id }}" data-scope-mode="{{ $tool->program_scope_mode->value }}" data-program-ids="{{ $tool->programs->pluck('id')->implode(',') }}" @selected((string)($row['certification_tool_id'] ?? '') === (string)$tool->id)>{{ $tool->name }}{{ $tool->retired_at ? ' (retired)' : '' }}</option>
                        @endforeach
                    </select>
                    <div class="form-text" data-cert-tool-empty hidden>
                        <span data-cert-tool-empty-message></span>
                        <button type="button" class="btn btn-sm btn-link px-0" data-cert-goto="basics">Review program scope</button>
                    </div>
                    <div class="text-danger small" data-cert-tool-warning hidden>Selected tool does not share this certificate’s program scope. Choose a compatible tool or correct the scope.</div>
                </x-form-field>
                <input type="hidden" name="{{ $field }}[requires_passing]" value="0">
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="req-{{ $reqIndex }}-passing" name="{{ $field }}[requires_passing]" value="1" @checked(filter_var($row['requires_passing'] ?? true, FILTER_VALIDATE_BOOLEAN)) data-cert-passing>
                    <label class="form-check-label" for="req-{{ $reqIndex }}-passing">Only coach-marked passing submissions count</label>
                </div>
                <fieldset class="border rounded p-3 mb-3">
                    <legend class="float-none w-auto fs-6 px-1">Submission coverage and quotas</legend>
                    <p class="text-muted small">All rules use the same eligible submission pool. One submission can count toward the total, its phase coverage, and a review or observation quota. Tool phases describe submissions; initial and renewal are certificate stages.</p>
                    <div data-cert-rules>
                        @foreach($row['dimension_rules'] ?? [] as $ruleIndex => $rule)
                            @include('admin.certificates.partials.dimension-rule-row', ['row' => $rule])
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cert-add="rule">Add coverage or quota</button>
                    <p class="form-text mb-0" data-cert-add-rule-help></p>
                    <template data-cert-rule-template>@include('admin.certificates.partials.dimension-rule-row', ['row' => [], 'ruleIndex' => '__RULE__'])</template>
                </fieldset>
            </div>
            <x-form-field label="Group" :for="'req-'.$reqIndex.'-group'" :name="$prefix.'.group_index'">
                <select class="form-select" id="req-{{ $reqIndex }}-group" name="{{ $field }}[group_index]" data-cert-group-select data-requirement-group-select>
                    <option value="">Individually required</option>
                    @foreach($groupOptions as $groupIndex => $groupLabel)<option value="{{ $groupIndex }}" @selected((string)($row['group_index'] ?? '') === (string)$groupIndex)>{{ $groupLabel }}</option>@endforeach
                </select>
                <button type="button" class="btn btn-sm btn-link px-0" data-cert-new-group>Place in a new group</button>
            </x-form-field>
            <x-form-field label="Threshold guidance" :for="'req-'.$reqIndex.'-threshold'" :name="$prefix.'.threshold_note'" help="Recorded guidance only. Coaches judge passing; scores are not calculated automatically.">
                <input class="form-control" id="req-{{ $reqIndex }}-threshold" name="{{ $field }}[threshold_note]" value="{{ $row['threshold_note'] ?? '' }}" maxlength="255" data-cert-threshold>
            </x-form-field>
            <x-form-field label="Author notes" :for="'req-'.$reqIndex.'-notes'" :name="$prefix.'.notes'">
                <textarea class="form-control" id="req-{{ $reqIndex }}-notes" name="{{ $field }}[notes]" rows="2" data-cert-notes>{{ $row['notes'] ?? '' }}</textarea>
            </x-form-field>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" data-cert-done>Done editing requirement</button>
        <span class="text-muted small ms-2">Save the certificate to keep changes.</span>
    </div>
</article>
