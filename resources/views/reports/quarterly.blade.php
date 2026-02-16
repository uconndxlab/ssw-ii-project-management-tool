@extends('layouts.app')

@section('title', 'Quarterly Report')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Quarterly Engagement Report</h1>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.quarterly') }}" 
                      hx-get="{{ route('reports.quarterly') }}" 
                      hx-target="#report-results" 
                      hx-swap="innerHTML">
                    
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="year" class="form-label">Year</label>
                            <select class="form-select" id="year" name="year">
                                @foreach($years as $year)
                                    <option value="{{ $year }}" {{ $year == $selectedYear ? 'selected' : '' }}>
                                        {{ $year }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="quarter" class="form-label">Quarter</label>
                            <select class="form-select" id="quarter" name="quarter">
                                <option value="1" {{ $selectedQuarter == 1 ? 'selected' : '' }}>Q1 (Jan-Mar)</option>
                                <option value="2" {{ $selectedQuarter == 2 ? 'selected' : '' }}>Q2 (Apr-Jun)</option>
                                <option value="3" {{ $selectedQuarter == 3 ? 'selected' : '' }}>Q3 (Jul-Sep)</option>
                                <option value="4" {{ $selectedQuarter == 4 ? 'selected' : '' }}>Q4 (Oct-Dec)</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                        </div>

                        <div class="col-md-3 text-end">
                            <small class="text-muted">
                                {{ $dateRange['start']->format('M d, Y') }} - {{ $dateRange['end']->format('M d, Y') }}
                            </small>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="report-results">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        Q{{ $selectedQuarter }} {{ $selectedYear }} Engagement Summary
                    </h5>
                </div>
                <div class="card-body">
                    @if(count($projectData) > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Organization</th>
                                    <th class="text-end">TA Hours</th>
                                    <th class="text-end">Coaching Hours</th>
                                    <th class="text-end">Training Hours</th>
                                    <th class="text-end"><strong>Total Hours</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $totalTA = 0;
                                    $totalCoaching = 0;
                                    $totalTraining = 0;
                                    $grandTotal = 0;
                                @endphp

                                @foreach($projectData as $data)
                                @php
                                    $totalTA += $data['technical_assistance'];
                                    $totalCoaching += $data['coaching'];
                                    $totalTraining += $data['training'];
                                    $grandTotal += $data['total'];
                                @endphp
                                <tr>
                                    <td>{{ $data['project']->name }}</td>
                                    <td>{{ $data['project']->organization->name }}</td>
                                    <td class="text-end">{{ number_format($data['technical_assistance'], 2) }}</td>
                                    <td class="text-end">{{ number_format($data['coaching'], 2) }}</td>
                                    <td class="text-end">{{ number_format($data['training'], 2) }}</td>
                                    <td class="text-end"><strong>{{ number_format($data['total'], 2) }}</strong></td>
                                </tr>
                                @endforeach

                                <tr class="table-secondary">
                                    <td colspan="2"><strong>TOTAL</strong></td>
                                    <td class="text-end"><strong>{{ number_format($totalTA, 2) }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($totalCoaching, 2) }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($totalTraining, 2) }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($grandTotal, 2) }}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <p class="text-muted mb-0">
                            <small>
                                @if(!auth()->user()->isAdmin())
                                    Report shows only projects you are assigned to.
                                @else
                                    Report shows all projects.
                                @endif
                            </small>
                        </p>
                    </div>
                    @else
                    <p class="text-muted text-center py-4 mb-0">
                        No engagements found for Q{{ $selectedQuarter }} {{ $selectedYear }}
                    </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
