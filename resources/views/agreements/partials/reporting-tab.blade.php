<div class="row g-4">
    <div class="col-lg-4">
        <section class="card shadow-sm entity-show-card" aria-labelledby="report-new-heading">
            <div class="card-header py-2 px-3"><h2 class="h5 mb-0" id="report-new-heading">Basic activity export</h2></div>
            <div class="card-body">
                <p class="small mb-2">One row per activity, with the essentials below.</p>
                <h3 class="app-subhead mb-2">Columns</h3>
                <ul class="small text-muted ps-3 mb-3">
                    <li>Date, activity family and type</li>
                    <li>Agreements covered</li>
                    <li>Organizations</li>
                    <li>Completions</li>
                    <li>Logged by</li>
                    <li>Internal only, cancelled, not yet complete</li>
                    <li>One column per logging field</li>
                </ul>
                <form method="POST" action="{{ route('agreements.reports.store', $agreement) }}">
                    @csrf
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-download me-2" aria-hidden="true"></i>Download CSV</button>
                        <span class="text-muted small">{{ $reportActivityCount }} {{ \Illuminate\Support\Str::plural('activity', $reportActivityCount) }}</span>
                    </div>
                </form>
            </div>
        </section>
    </div>
    <div class="col-lg-8">
        <section class="card shadow-sm entity-show-card" aria-labelledby="report-history-heading">
            <div class="card-header py-2 px-3 d-flex align-items-center gap-2 flex-wrap">
                <h2 class="h5 mb-0" id="report-history-heading">Requested reports</h2>
                <span class="app-count">{{ $reportRequests->count() }}</span>
            </div>
            <div class="card-body">
                @if($reportRequests->isEmpty())
                    <p class="text-muted mb-0">No reports requested yet.</p>
                @else
                    <div class="table-responsive app-embedded-table">
                        <table class="table mb-0 align-top">
                            <thead><tr><th class="text-nowrap" scope="col">Requested</th><th scope="col">Requested by</th><th scope="col">Report</th><th scope="col">Format</th></tr></thead>
                            <tbody>
                                @foreach($reportRequests as $reportRequest)
                                    <tr>
                                        <td class="text-nowrap">{{ $reportRequest->created_at->format('M j, Y') }}<div class="text-muted small">{{ $reportRequest->created_at->format('g:i A') }}</div></td>
                                        <td>
                                            <x-user-link :user="$reportRequest->user" :label="$reportRequest->user_name" class="app-link" />
                                            <div class="text-muted small">
                                                {{ $reportRequest->user_email }}
                                                @if($reportRequest->user_id === null)
                                                    · account removed
                                                @endif
                                            </div>
                                        </td>
                                        <td>{{ $reportRequest->type->label() }}</td>
                                        <td><span class="badge app-tag">{{ $reportRequest->format->label() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-3 mb-0">Names and emails are recorded as they were when the report was requested.</p>
                @endif
            </div>
        </section>
    </div>
</div>
