<?php

namespace App\Jobs;

use App\Mail\Checks\EndpointInspector;
use App\Mail\Network\ProbePermits;
use App\Mail\Scans\ScanRepository;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\PolicySnapshot;
use App\Mail\Value\ScanBudget;
use App\Models\ScanEndpoint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProbeEndpoint implements ShouldQueue
{
    use Queueable;

    public int $timeout = 45;

    public int $tries = 0;

    public int $maxExceptions = 2;

    public bool $failOnTimeout = true;

    public function __construct(public int $endpointId)
    {
        $this->onQueue('endpoints');
    }

    public function retryUntil(): \DateTimeInterface
    {
        return ScanEndpoint::find($this->endpointId)?->scan?->deadline ?? now();
    }

    public function backoff(): array
    {
        return [5, 15];
    }

    public function handle(ScanRepository $repository, ProbePermits $permits, EndpointInspector $inspector): void
    {
        $endpoint = ScanEndpoint::with('scan')->find($this->endpointId);
        if (! $endpoint || $endpoint->scan->terminal() || $endpoint->scan->deadline <= now()) {
            return;
        }
        $target = EndpointTarget::fromArray($endpoint->toArray());
        $permit = $permits->acquire((string) $endpoint->scan_id, $target);
        if (! $permit) {
            if ($this->job) {
                $this->release(2);
            }

return;
        }$lease = null;
        try {
            $lease = $repository->claimEndpoint($endpoint);
            if (! $lease) {
                return;
            }
            $budget = new ScanBudget(min((float) config('mail_scan.endpoint_seconds', 30), max(0, $endpoint->scan->deadline->getTimestamp() - now()->timestamp)));
            $findings = $inspector->inspect($target, PolicySnapshot::fromArray($endpoint->scan->policy_snapshot ?? []), $budget);
            $repository->completeEndpoint($endpoint, $lease, $findings);
        } catch (\Throwable $e) {
            if ($lease) {
                $repository->abandonEndpoint($endpoint, $lease);
            }throw $e;
        } finally {
            $permits->release($permit);
        }
    }
}
