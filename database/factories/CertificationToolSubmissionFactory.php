<?php

namespace Database\Factories;

use App\Enums\CertificateTagOutcome;
use App\Models\ActivityCertificationCandidate;
use App\Models\CertificationTool;
use App\Models\CertificationToolSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificationToolSubmission> */
class CertificationToolSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return ['activity_certification_candidate_id' => ActivityCertificationCandidate::factory(), 'certification_tool_id' => CertificationTool::factory(), 'outcome' => CertificateTagOutcome::Passed];
    }
}
