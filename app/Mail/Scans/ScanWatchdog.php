<?php

namespace App\Mail\Scans;

use App\Mail\Value\Finding;
use App\Models\Scan;
use App\Models\ScanOutbox;
use Illuminate\Support\Facades\DB;

class ScanWatchdog
{
    public function reconcile(): int
    {
        $count = 0;
        foreach (Scan::whereIn('status', ['queued', 'running'])->get() as $scan) {
            DB::transaction(function () use ($scan, &$count) {
                $locked = Scan::lockForUpdate()->find($scan->id);
                if (! $locked || $locked->terminal()) {
                    return;
                }
                if ($locked->deadline <= now()) {
                    foreach ($locked->endpoints()->whereIn('status', ['queued', 'running'])->lockForUpdate()->get() as $endpoint) {
                        $endpoint->update(['status' => 'expired', 'lease_token' => null, 'lease_expires_at' => null]);
                        app(ScanRepository::class)->putFinding($locked, $endpoint, Finding::make('deadline', 'inconclusive', 'Endpoint deadline expired', 'The worker did not return observations within the scan window.'));
                    }
                    $useful = $locked->findings()->where('check_key', '!=', 'deadline')->exists();
                    $locked->update(['status' => $useful ? 'partial' : 'failed', 'finished_at' => now(), 'terminal_error' => 'Scan deadline reached.']);
                    $count++;

                    return;
                }
                foreach ($locked->endpoints()->where('status', 'running')->where('lease_expires_at', '<=', now())->lockForUpdate()->get() as $endpoint) {
                    if ($endpoint->attempts >= 2) {
                        $endpoint->update(['status' => 'failed', 'lease_token' => null, 'lease_expires_at' => null]);
                        app(ScanRepository::class)->putFinding($locked, $endpoint, Finding::make('worker_failure', 'inconclusive', 'Endpoint worker lease expired', 'Retry allowance exhausted.'));
                    } else {
                        $endpoint->update(['status' => 'queued', 'lease_token' => null, 'lease_expires_at' => null]);
                        ScanOutbox::create(['scan_id' => $locked->id, 'scan_endpoint_id' => $endpoint->id, 'job_kind' => 'endpoint']);
                    }$count++;
                }
            });
            app(ScanFinalizer::class)->finalize($scan);
        }
        app(OutboxDispatcher::class)->dispatchPending();

        return $count;
    }
}
