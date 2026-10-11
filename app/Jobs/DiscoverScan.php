<?php

namespace App\Jobs;

use App\Mail\Scans\ScanCoordinator;
use App\Models\Scan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DiscoverScan implements ShouldQueue
{
    use Queueable;

    public int $timeout = 240;

    public int $tries = 2;

    public bool $failOnTimeout = true;

    public function __construct(public int $scanId)
    {
        $this->onQueue('discovery');
    }

    public function backoff(): array
    {
        return [5, 15];
    }

    public function handle(ScanCoordinator $coordinator): void
    {
        if ($scan = Scan::find($this->scanId)) {
            $coordinator->discover($scan);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $scan = Scan::find($this->scanId);
        if ($scan && ! $scan->terminal() && $scan->routing_snapshot === null) {
            $scan->update(['status' => 'failed', 'finished_at' => now(), 'terminal_error' => 'Discovery worker could not complete the scan.']);
        }
    }
}
