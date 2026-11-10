@foreach(\App\Enums\CertificateRequirementPhase::cases() as $phase)
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h5">{{ $phase->label() }}</h2>
            @if($phase === \App\Enums\CertificateRequirementPhase::Renewal && $certificate->renewal_matches_initial)
                <p class="text-muted mb-0">Renewal matches initial requirements.</p>
            @else
                @php
                    $phaseRequirements = $certificate->requirements->where('phase', $phase);
                    $phaseGroups = $certificate->requirementGroups->where('phase', $phase);
                @endphp
                @foreach($phaseGroups as $group)
                    <div class="border rounded p-3 mb-3">
                        <h3 class="h6 mb-1">{{ $group->label }}</h3>
                        <p class="small text-muted">{{ $group->satisfy_mode->label() }}{{ $group->satisfy_mode->requiresCount() ? ' · ' . $group->required_count . ' required' : '' }}</p>
                        @foreach($phaseRequirements->where('certificate_requirement_group_id', $group->id) as $requirement)
                            @include('admin.certificates.partials.show-requirement')
                        @endforeach
                    </div>
                @endforeach
                @foreach($phaseRequirements->whereNull('certificate_requirement_group_id') as $requirement)
                    @include('admin.certificates.partials.show-requirement')
                @endforeach
                @if($phaseRequirements->isEmpty() && $phaseGroups->isEmpty())
                    <p class="text-muted mb-0">No requirements configured.</p>
                @endif
            @endif
        </div>
    </div>
@endforeach
