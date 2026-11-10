<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5">Overview</h2>
        @if($certificate->description)<p>{{ $certificate->description }}</p>@endif
        <dl class="app-summary-facts mb-3">
            <div><dt>Validity</dt><dd>{{ $certificate->validity_months ? $certificate->validity_months . ' months' : 'No expiry' }}</dd></div>
            <div><dt>Renewal window</dt><dd>{{ $certificate->default_window_months ? $certificate->default_window_months . ' months' : 'All time' }}</dd></div>
            <div><dt>Program scope</dt><dd>{{ $certificate->program_scope_mode->label() }}</dd></div>
            <div><dt>Projects</dt><dd><x-table-badge-list kind="project" :items="$certificate->projects" :empty-label="$certificate->program_scope_mode->value === 'all' ? 'All projects' : 'No projects'" /></dd></div>
            <div><dt>Programs</dt><dd><x-table-badge-list kind="program" :items="$certificate->programs" :empty-label="$certificate->program_scope_mode->value === 'all' ? 'All programs' : 'No programs'" /></dd></div>
        </dl>
        <h3 class="app-subhead">Prerequisites · {{ $certificate->prerequisite_mode === 'any' ? 'Any one' : 'All required' }}</h3>
        <ul class="mb-3">
            @forelse($certificate->prerequisites as $prerequisite)
                <li>@can('view', $prerequisite)<a href="{{ route('certificates.show', $prerequisite) }}">{{ $prerequisite->name }}</a>@else{{ $prerequisite->name }}@endcan</li>
            @empty
                <li>None</li>
            @endforelse
        </ul>
        <h3 class="app-subhead">Roles</h3>
        <ul class="mb-3">
            @forelse($globalRoles->concat($certificate->roles) as $role)
                <li>{{ $role->name }} <span class="text-muted small">{{ $role->certificate_id === null ? 'Global' : 'Certificate' }}{{ $role->active ? '' : ' · Inactive' }}</span></li>
            @empty
                <li>None</li>
            @endforelse
        </ul>
        <h3 class="app-subhead">Enrollment totals · all candidates</h3>
        <dl class="app-record-metrics mb-0">
            @forelse($lifecycleTotals as $label => $count)
                <div><dt>{{ $label }}</dt><dd>{{ $count }}</dd></div>
            @empty
                <div><dt>Total</dt><dd>0</dd></div>
            @endforelse
        </dl>
    </div>
</div>
