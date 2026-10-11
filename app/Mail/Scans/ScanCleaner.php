<?php

namespace App\Mail\Scans;

use App\Models\Scan;

class ScanCleaner
{
    public function prune(): int
    {
        return Scan::where('created_at', '<', now()->subDays(config('mail_scan.retention_days', 7)))->delete();
    }
}
