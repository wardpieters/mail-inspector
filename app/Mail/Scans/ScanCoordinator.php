<?php

namespace App\Mail\Scans;

use App\Mail\Checks\CaaCheck;
use App\Mail\Checks\DkimCheck;
use App\Mail\Checks\DmarcCheck;
use App\Mail\Checks\SpfCheck;
use App\Mail\Checks\TransportPolicies;
use App\Mail\Dns\MxDiscovery;
use App\Mail\Value\Finding;
use App\Mail\Value\PolicySnapshot;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\ScanInput;
use App\Models\Scan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ScanCoordinator
{
    public function __construct(private MxDiscovery $routing, private CaaCheck $caa, private SpfCheck $spf, private DkimCheck $dkim, private DmarcCheck $dmarc, private TransportPolicies $policies, private ScanRepository $repository) {}

    public function discover(Scan $scan): void
    {
        $lock = Cache::lock('scan:discover:'.$scan->id, 250);
        if (! $lock->get()) {
            return;
        }
        try {
            $scan->refresh();
            if ($scan->terminal() || $scan->deadline <= now() || $scan->routing_snapshot !== null) {
                return;
            }
            $scan->update(['status' => 'running', 'started_at' => $scan->started_at ?? now(), 'capabilities' => ['php' => PHP_VERSION, 'openssl' => OPENSSL_VERSION_TEXT, 'ipv6_socket_supported' => defined('AF_INET6'), 'ipv6_egress' => 'determined by per-address probes', 'dns_trusted' => (bool) config('mail_scan.dns_trusted'), 'relay_probe_configured' => (bool) (config('mail_scan.relay_sender') && config('mail_scan.relay_recipient'))]]);
            $input = new ScanInput($scan->domain, $scan->selectors);
            $budget = new ScanBudget(min(230, max(0, $scan->deadline->getTimestamp() - now()->timestamp)));
            $routing = $this->routing->discover($input, $budget);
            $domainFindings = [];
            foreach (['caa' => $this->caa, 'spf' => $this->spf, 'dkim' => $this->dkim, 'dmarc' => $this->dmarc] as $key => $check) {
                try {
                    $domainFindings = array_merge($domainFindings, $check->analyze($key === 'caa' ? $input->domain : $input, $budget));
                } catch (\Throwable $e) {
                    $domainFindings[] = Finding::make($key, 'inconclusive', 'Domain check did not complete', substr($e->getMessage(), 0, 300));
                }
            }
            foreach (array_unique(array_map(fn ($t) => $t->mx, $routing->targets)) as $mx) {
                try {
                    $domainFindings = array_merge($domainFindings, $this->caa->analyze($mx, $budget));
                } catch (\Throwable $e) {
                    $domainFindings[] = Finding::make('caa', 'inconclusive', 'MX CAA check did not complete', substr($e->getMessage(), 0, 300), [], null, 'none', $mx);
                }
            }
            try {
                $policies = $this->policies->discover($input, $routing, $budget);
            } catch (\Throwable $e) {
                $policies = new PolicySnapshot(['state' => 'error'], [], [Finding::make('transport_policies', 'inconclusive', 'Transport policy discovery incomplete', substr($e->getMessage(), 0, 300))]);
            }
            $this->repository->planEndpoints($scan, $routing, new PolicySnapshot($policies->mtaSts, $policies->tlsaByMx, [...$domainFindings, ...$policies->findings]));
            app(ScanFinalizer::class)->finalize($scan);
            DB::afterCommit(fn () => app(OutboxDispatcher::class)->dispatchPending());
        } finally {
            $lock->release();
        }
    }
}
