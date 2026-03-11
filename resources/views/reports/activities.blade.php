@extends('layouts.app')

@section('title', 'Activity Report')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Activity Report</h1>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.activities') }}" 
                      hx-get="{{ route('reports.activities') }}" 
                      hx-target="#report-results" 
                      hx-swap="innerHTML">
                    
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="start_date" 
                                   name="start_date" 
                                   value="{{ $startDate }}"
                                   required>
                        </div>

                        <div class="col-md-2">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="end_date" 
                                   name="end_date" 
                                   value="{{ $endDate }}"
                                   required>
                        </div>

                        <div class="col-md-2">
                            <label for="agreement_id" class="form-label">Agreement</label>
                            <select class="form-select" id="agreement_id" name="agreement_id">
                                <option value="">All Agreements</option>
                                @foreach($visibleAgreements as $agreement)
                                    <option value="{{ $agreement->id }}" {{ $agreementId == $agreement->id ? 'selected' : '' }}>
                                        {{ $agreement->name }} ({{ $agreement->organization->name }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="program_id" class="form-label">Program</label>
                            <select class="form-select" id="program_id" name="program_id">
                                <option value="">All Programs</option>
                                @foreach($programs as $program)
                                    <option value="{{ $program->id }}" {{ $programId == $program->id ? 'selected' : '' }}>
                                        {{ $program->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="contact_family_id" class="form-label">Contact Family</label>
                            <select class="form-select" id="contact_family_id" name="contact_family_id">
                                <option value="">All Families</option>
                                @foreach($contactFamilies as $family)
                                    <option value="{{ $family->id }}" {{ $contactFamilyId == $family->id ? 'selected' : '' }}>
                                        {{ $family->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="activity_type_id" class="form-label">Activity Type</label>
                            <select class="form-select" id="activity_type_id" name="activity_type_id">
                                <option value="">All Types</option>
                                @foreach($activityTypes as $type)
                                    <option value="{{ $type->id }}" {{ $activityTypeId == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="report-results">
    @include('reports.partials.results-table')
</div>
@endsection
