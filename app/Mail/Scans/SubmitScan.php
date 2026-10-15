<?php

namespace App\Mail\Scans;

use App\Mail\Value\ScanInput;
use App\Models\Scan;
use App\Models\ScanOutbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitScan
{
    public const int COOLDOWN_SECONDS = 180;

    public function submit(ScanInput $input, bool $hiddenFromOverview = false): Scan
    {
        return Cache::lock('submit:'.hash('sha256', $input->domain), 10)->block(3, fn () => DB::transaction(function () use ($input, $hiddenFromOverview) {
            $existing = Scan::where('domain', $input->domain)
                ->where(function (Builder $query): void {
                    $query->where('created_at', '>', now()->subSeconds(self::COOLDOWN_SECONDS))
                        ->orWhere(function (Builder $active): void {
                            $active->whereIn('status', ['queued', 'running'])->where('deadline', '>', now());
                        });
                })
                ->latest('id')->first();
            if ($existing) {
                if ($hiddenFromOverview && ! $existing->hidden_from_overview) {
                    $existing->update(['hidden_from_overview' => true]);
                }

                return $existing;
            }
            $scan = Scan::create(['public_id' => (string) Str::uuid(), 'domain' => $input->domain, 'selectors' => $input->selectors, 'hidden_from_overview' => $hiddenFromOverview, 'status' => 'queued', 'deadline' => now()->addSeconds(config('mail_scan.scan_seconds', 300))]);
            ScanOutbox::create(['scan_id' => $scan->id, 'job_kind' => 'discover']);
            DB::afterCommit(function (): void {
                try {
                    app(OutboxDispatcher::class)->dispatchPending();
                } catch (\Throwable $e) {
                    report($e);
                }
            });

            return $scan;
        }));
    }
}
