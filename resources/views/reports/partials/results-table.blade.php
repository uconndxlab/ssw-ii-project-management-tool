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
                                <th class="text-end">Event Hours</th>
                                <th class="text-end">Prep Hours</th>
                                <th class="text-end">Follow-Up Hours</th>
                                <th class="text-end"><strong>Total Hours</strong></th>
                                <th class="text-end">Participants</th>
                                <th class="text-center">Activities</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $totalEvent = 0;
                                $totalPrep = 0;
                                $totalFollowup = 0;
                                $grandTotal = 0;
                                $totalParticipants = 0;
                                $totalActivities = 0;
                            @endphp

                            @foreach($agreementData as $data)
                            @php
                                $totalEvent += $data['event_hours'];
                                $totalPrep += $data['prep_hours'];
                                $totalFollowup += $data['followup_hours'];
                                $grandTotal += $data['total_hours'];
                                $totalParticipants += $data['participant_count'];
                                $totalActivities += $data['activity_count'];
                            @endphp
                            <tr>
                                <td>{{ $data['agreement']->name }}</td>
                                <td>{{ $data['agreement']->organization->name }}</td>
                                <td class="text-end">{{ number_format($data['event_hours'], 2) }}</td>
                                <td class="text-end">{{ number_format($data['prep_hours'], 2) }}</td>
                                <td class="text-end">{{ number_format($data['followup_hours'], 2) }}</td>
                                <td class="text-end"><strong>{{ number_format($data['total_hours'], 2) }}</strong></td>
                                <td class="text-end">{{ number_format($data['participant_count']) }}</td>
                                <td class="text-center">{{ $data['activity_count'] }}</td>
                            </tr>
                            @endforeach

                            <tr class="table-secondary">
                                <td colspan="2"><strong>TOTAL</strong></td>
                                <td class="text-end"><strong>{{ number_format($totalEvent, 2) }}</strong></td>
                                <td class="text-end"><strong>{{ number_format($totalPrep, 2) }}</strong></td>
                                <td class="text-end"><strong>{{ number_format($totalFollowup, 2) }}</strong></td>
                                <td class="text-end"><strong>{{ number_format($grandTotal, 2) }}</strong></td>
                                <td class="text-end"><strong>{{ number_format($totalParticipants) }}</strong></td>
                                <td class="text-center"><strong>{{ $totalActivities }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <p class="text-muted mb-0">
                        <small>
                            @if(!auth()->user()->isAdmin())
                                Report shows only agreements you are assigned to.
                            @else
                                Report shows all agreements.
                            @endif
                        </small>
                    </p>
                </div>
                @else
                <p class="text-muted text-center py-4 mb-0">
                    No activities found for the selected date range
                    @if($agreementId)
                        and agreement
                    @endif
                </p>
                @endif
            </div>
        </div>
    </div>
</div>
