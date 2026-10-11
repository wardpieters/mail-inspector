<?php

namespace App\Mail\Scans;

use App\Mail\Enums\EndpointStatus;
use App\Mail\Value\Finding;
use App\Mail\Value\PolicySnapshot;
use App\Mail\Value\RoutingSnapshot;
use App\Models\Scan;
use App\Models\ScanEndpoint;
use App\Models\ScanFinding;
use App\Models\ScanOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScanRepository
{
    public function putFinding(Scan $scan, ?ScanEndpoint $endpoint, Finding $finding): void
    {
        ScanFinding::updateOrCreate(['scan_id' => $scan->id, 'check_key' => $finding->key, 'scope_key' => ($endpoint ? 'endpoint:'.$endpoint->id.':' : '').$finding->scope], ['scan_endpoint_id' => $endpoint?->id, 'outcome' => $finding->outcome->value, 'severity' => $finding->severity->value, 'title' => $finding->title, 'explanation' => $finding->explanation, 'recommendation' => $finding->recommendation, 'evidence' => $finding->evidence, 'duration_ms' => $finding->durationMs]);
    }

    public function planEndpoints(Scan $scan, RoutingSnapshot $routing, PolicySnapshot $policies): void
    {
        DB::transaction(function () use ($scan, $routing, $policies) {
            $locked = Scan::lockForUpdate()->find($scan->id);
            if (! $locked || $locked->terminal() || $locked->routing_snapshot !== null) {
                return;
            }
            $locked->update(['routing_snapshot' => $routing->jsonSerialize(), 'policy_snapshot' => $policies->jsonSerialize()]);
            foreach ([...$routing->findings, ...$policies->findings] as $finding) {
                $this->putFinding($locked, null, $finding);
            }
            foreach ($routing->targets as $target) {
                $endpoint = $locked->endpoints()->create($target->jsonSerialize() + ['status' => 'queued']);
                ScanOutbox::create(['scan_id' => $locked->id, 'scan_endpoint_id' => $endpoint->id, 'job_kind' => 'endpoint']);
            }
            if ($routing->nullMx) {
                foreach (['connect', 'answer', 'helo', 'tls', 'secure', 'cert', 'from', 'mtasts', 'dane', 'fcrdns', 'open_relay'] as $key) {
                    $this->putFinding($locked, null, Finding::make($key, 'not_applicable', 'SMTP check not applicable', 'This domain publishes valid null MX.', [], null, 'none', 'null_mx'));
                }
            }
        });
    }

    public function claimEndpoint(ScanEndpoint $endpoint): ?string
    {
        return DB::transaction(function () use ($endpoint) {
            $scan = Scan::lockForUpdate()->find($endpoint->scan_id);
            $locked = ScanEndpoint::lockForUpdate()->find($endpoint->id);
            if (! $scan || ! $locked || $scan->terminal() || $scan->deadline <= now() || in_array($locked->status, [EndpointStatus::Completed, EndpointStatus::Failed, EndpointStatus::Expired], true) || ($locked->status === EndpointStatus::Running && $locked->lease_expires_at > now())) {
                return null;
            }
            if ($locked->attempts >= 2) {
                $locked->update(['status' => 'failed', 'lease_token' => null, 'lease_expires_at' => null]);

                return null;
            }
            $token = (string) Str::uuid();
            $locked->update(['status' => 'running', 'lease_token' => $token, 'lease_expires_at' => now()->addSeconds(60), 'attempts' => $locked->attempts + 1]);

            return $token;
        });
    }

    public function completeEndpoint(ScanEndpoint $endpoint, string $leaseToken, array $findings): void
    {
        DB::transaction(function () use ($endpoint, $leaseToken, $findings) {
            $scan = Scan::lockForUpdate()->find($endpoint->scan_id);
            $locked = ScanEndpoint::lockForUpdate()->find($endpoint->id);
            if (! $scan || ! $locked || $scan->terminal() || $scan->deadline <= now() || $locked->status !== EndpointStatus::Running || $locked->lease_token !== $leaseToken || $locked->lease_expires_at <= now()) {
                return;
            }
            foreach ($findings as $finding) {
                $this->putFinding($scan, $locked, $finding);
            }$locked->update(['status' => 'completed', 'lease_token' => null, 'lease_expires_at' => null]);
        });
        app(ScanFinalizer::class)->finalize($endpoint->scan);
    }

    public function abandonEndpoint(ScanEndpoint $endpoint, string $leaseToken): void
    {
        DB::transaction(function () use ($endpoint, $leaseToken) {
            $scan = Scan::lockForUpdate()->find($endpoint->scan_id);
            $locked = ScanEndpoint::lockForUpdate()->find($endpoint->id);
            if (! $scan || $scan->terminal() || ! $locked || $locked->lease_token !== $leaseToken) {
                return;
            }
            $failed = $locked->attempts >= 2;
            $locked->update(['status' => $failed ? 'failed' : 'queued', 'lease_token' => null, 'lease_expires_at' => null]);
            if ($failed) {
                $this->putFinding($scan, $locked, Finding::make('worker_failure', 'inconclusive', 'Worker could not complete this endpoint', 'Infrastructure retries were exhausted.'));
            }
        });
        app(ScanFinalizer::class)->finalize($endpoint->scan);
    }
}
