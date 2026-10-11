<?php

namespace App\Mail\Scans;

use App\Jobs\DiscoverScan;
use App\Jobs\ProbeEndpoint;
use App\Models\ScanOutbox;
use Illuminate\Support\Facades\Cache;

class OutboxDispatcher
{
    public function dispatchPending(): int
    {
        $lock = Cache::lock('scan:outbox', 30);
        if (! $lock->get()) {
            return 0;
        }$count = 0;
        try {
            foreach (ScanOutbox::whereNull('dispatched_at')->orderBy('id')->limit(100)->get() as $row) {
                if ($row->job_kind === 'discover') {
                    DiscoverScan::dispatch($row->scan_id);
                } else {
                    ProbeEndpoint::dispatch($row->scan_endpoint_id);
                }
                $row->update(['dispatched_at' => now()]);
                $count++;
            }
        } finally {
            $lock->release();
        }

        return $count;
    }
}
