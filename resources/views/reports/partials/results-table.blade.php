<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    Activity Summary: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                </h5>
            </div>
            <div class="card-body">
                @if(count($agreementData) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Agreement</th>
                                <th>Organization</th>
                                <th class="text-center">Activities</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totalActivities = 0; @endphp

                            @foreach($agreementData as $data)
                            @php $totalActivities += $data['activity_count']; @endphp
                            <tr>
                                <td>{{ $data['agreement']->name }}</td>
                                <td>{{ $data['agreement']->organization->name ?? '—' }}</td>
                                <td class="text-center">{{ $data['activity_count'] }}</td>
                            </tr>
                            @endforeach

                            <tr class="table-secondary">
                                <td colspan="2"><strong>TOTAL</strong></td>
                                <td class="text-center"><strong>{{ $totalActivities }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <p class="text-muted mb-0">
                        <small>
                            @if(!auth()->user()->access()->hasSystemView())
                                Report shows only records in your view scope.
                            @else
                                Report shows all agreements.
                            @endif
                        </small>
                    </p>
                </div>
                @else
                <p class="text-muted">No activities found for the selected criteria.</p>
                @endif
            </div>
        </div>
    </div>
</div>
