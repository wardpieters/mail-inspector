<?php

namespace App\Mail\Scans;

use App\Models\Scan;
use Illuminate\Support\Facades\DB;

class ScanFinalizer
{
    public function finalize(Scan $scan): void
    {
        DB::transaction(function () use ($scan) {
            $locked = Scan::lockForUpdate()->find($scan->id);
            if (! $locked || $locked->terminal() || $locked->routing_snapshot === null) {
                return;
            }
            if ($locked->endpoints()->whereIn('status', ['queued', 'running'])->exists()) {
                return;
            }
            $partial = $locked->endpoints()->whereIn('status', ['failed', 'expired'])->exists() || ($locked->routing_snapshot['omitted'] ?? 0) > 0 || $locked->findings()->where('outcome', 'inconclusive')->exists();
            $locked->update(['status' => $partial ? 'partial' : 'completed', 'finished_at' => now()]);
        });
    }
}
