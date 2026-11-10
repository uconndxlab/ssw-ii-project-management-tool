<div class="border-top py-3">
    <h4 class="h6 mb-1">{{ $requirement->label ?: $requirement->kind->label() }}</h4>
    <p class="small text-muted mb-1">
        {{ $requirement->kind->label() }}
        @if($requirement->kind !== \App\Enums\CertificateRequirementKind::Attestation)
            · Target: {{ $requirement->target_count }}
            · {{ ($requirement->window_months ?? $certificate->default_window_months) ? ($requirement->window_months ?? $certificate->default_window_months) . ' month window' : 'All time' }}
        @endif
        @if($requirement->requires_passing) · Passing required @endif
    </p>
    @if($requirement->certificationTool)<p class="small mb-1">Tool: {{ $requirement->certificationTool->name }}</p>@endif
    @if($requirement->contactFamily || $requirement->activityType)<p class="small mb-1">Activity: {{ $requirement->contactFamily?->name }}{{ $requirement->activityType ? ' · ' . $requirement->activityType->name : '' }}</p>@endif
    @if($requirement->certificationRole)<p class="small mb-1">Role: {{ $requirement->certificationRole->name }}</p>@endif
    @foreach($requirement->dimensionRules as $rule)
        <p class="small mb-1">{{ $rule->dimension?->name }} · {{ $rule->mode->label() }}{{ $rule->mode->requiresMinCount() ? ': ' . $rule->min_count : '' }} · {{ $rule->dimension?->options->whereIn('id', $rule->option_ids)->pluck('label')->implode(', ') }}</p>
    @endforeach
    @if($requirement->threshold_note)<p class="small mb-1">Coach guidance: {{ $requirement->threshold_note }}</p>@endif
    @if($requirement->notes)<p class="small mb-0">{{ $requirement->notes }}</p>@endif
</div>
