<?php

namespace App\Http\Controllers;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Models\Agreement;
use App\Models\ReportRequest;
use App\Support\Reports\BasicActivityExport;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgreementReportController extends Controller
{
    public function store(Agreement $agreement): StreamedResponse
    {
        $this->authorize('create', [ReportRequest::class, $agreement]);
        $user = $this->actor();

        $agreement->reportRequests()->create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'type' => ReportType::BasicActivityExport,
            'format' => ReportFormat::Csv,
        ]);

        return response()->streamDownload(
            fn () => (new BasicActivityExport($agreement))->write(),
            Str::slug($agreement->name).'-activities-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
