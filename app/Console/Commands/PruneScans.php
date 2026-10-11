<?php

namespace App\Console\Commands;

use App\Mail\Scans\ScanCleaner;
use Illuminate\Console\Command;

class PruneScans extends Command
{
    protected $signature = 'scans:prune';

    protected $description = 'Prune expired public reports';

    public function handle(): int
    {
        $this->info((string) (app(ScanCleaner::class)->prune()).' records processed.');

        return self::SUCCESS;
    }
}
