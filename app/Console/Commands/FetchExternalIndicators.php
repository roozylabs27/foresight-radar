<?php

namespace App\Console\Commands;

use App\Services\ExternalIndicatorService;
use Illuminate\Console\Command;

/** Roadmap R3.6: queue a fetch for every active external indicator in every workspace (scheduled daily). */
class FetchExternalIndicators extends Command
{
    protected $signature = 'indicators:fetch-external';

    protected $description = 'Queue a fetch of every active external early warning indicator';

    public function handle(ExternalIndicatorService $sources): int
    {
        $queued = ExternalIndicatorService::due()->filter(fn ($indicator) => $sources->queue($indicator))->count();
        $this->info("{$queued} indikator eksternal diantrikan.");

        return self::SUCCESS;
    }
}
