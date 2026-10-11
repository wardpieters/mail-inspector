<?php

namespace App\Console\Commands;

use App\Mail\Scans\ScanWatchdog;
use Illuminate\Console\Command;

class ReconcileScans extends Command
{
    protected $signature = 'scans:reconcile';

    protected $description = 'Recover expired leases and finalize stalled scans';

    public function handle(): int
    {
        $this->info((string) (app(ScanWatchdog::class)->reconcile()).' records processed.');

        return self::SUCCESS;
    }
}
