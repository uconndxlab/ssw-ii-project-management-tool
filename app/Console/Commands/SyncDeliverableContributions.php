<?php

namespace App\Console\Commands;

use App\Models\Agreement;
use App\Services\DeliverableContributionService;
use Illuminate\Console\Command;

class SyncDeliverableContributions extends Command
{
    protected $signature = 'deliverable-contributions:sync
                            {agreement? : Agreement ID to sync}
                            {--all : Sync contributions for every agreement}';

    protected $description = 'Rebuild deliverable contributions from agreement activity history';

    public function handle(DeliverableContributionService $service): int
    {
        $syncAll = (bool) $this->option('all');
        $agreementId = $this->argument('agreement');

        if ($syncAll && $agreementId !== null) {
            $this->error('Pass either an agreement ID or --all, not both.');

            return self::FAILURE;
        }

        if (!$syncAll && $agreementId === null) {
            $this->error('Pass an agreement ID or use --all.');

            return self::FAILURE;
        }

        if ($syncAll) {
            $count = 0;

            Agreement::query()->orderBy('id')->each(function (Agreement $agreement) use ($service, &$count) {
                $service->syncForAgreement($agreement);
                $count++;
                $this->line("Synced agreement {$agreement->id}: {$agreement->name}");
            });

            $this->info("Synced {$count} agreement(s).");

            return self::SUCCESS;
        }

        $agreement = Agreement::query()->find($agreementId);

        if (!$agreement) {
            $this->error("Agreement {$agreementId} not found.");

            return self::FAILURE;
        }

        $service->syncForAgreement($agreement);
        $this->info("Synced contributions for agreement {$agreement->id}: {$agreement->name}");

        return self::SUCCESS;
    }
}
