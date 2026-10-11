<?php

namespace App\Console\Commands;

use App\Mail\Scans\OutboxDispatcher;
use Illuminate\Console\Command;

class DispatchScanOutbox extends Command
{
    protected $signature = 'scans:dispatch';

    protected $description = 'Dispatch saved scan work';

    public function handle(): int
    {
        $this->info((string) (app(OutboxDispatcher::class)->dispatchPending()).' records processed.');

        return self::SUCCESS;
    }
}
