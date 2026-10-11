<?php

namespace App\Mail\Scans;

use App\Mail\Enums\EndpointStatus;
use App\Models\Scan;
use Illuminate\Support\Collection;

class ReportAssessment
{
    public function assess(Scan $scan): array
    {
        $scan->loadMissing(['findings', 'endpoints']);
        $findings = $scan->findings;
        $endpoints = $scan->endpoints;
        $nullMx = (bool) ($scan->routing_snapshot['null_mx'] ?? false);
        $pending = $endpoints->whereIn('status', [EndpointStatus::Queued, EndpointStatus::Running])->count();
        $omitted = (int) ($scan->routing_snapshot['omitted'] ?? 0);
        $incomplete = $pending > 0 || $omitted > 0 || $endpoints->whereIn('status', [EndpointStatus::Failed, EndpointStatus::Expired])->isNotEmpty() || in_array($scan->status->value, ['queued', 'running', 'partial', 'failed'], true) || $findings->where('outcome', 'inconclusive')->isNotEmpty();
        $dimensions = [];
        foreach (['reachability' => ['mx', 'mx_no_address', 'connect', 'answer', 'helo', 'from', 'fcrdns'], 'transport' => ['dnssec', 'caa', 'tls', 'secure', 'cert', 'mtasts', 'dane', 'open_relay', 'authenticated_transport'], 'sender_authentication' => ['spf', 'dkim', 'dmarc', 'dmarc_reporting']] as $key => $checks) {
            $selected = $findings->whereIn('check_key', $checks);
            $dimensionIncomplete = $selected->isEmpty() || $selected->where('outcome', 'inconclusive')->isNotEmpty();
            $required = match ($key) {
                'reachability' => ['connect', 'answer', 'helo', 'from', 'fcrdns'],
                'transport' => ['tls', 'secure', 'cert', 'mtasts', 'dane'],
                default => [],
            };
            if ($key === 'transport') {
                foreach (['dnssec', 'caa'] as $check) {
                    $dimensionIncomplete = $dimensionIncomplete || ! $selected->contains('check_key', $check);
                }
            }
            if ($key === 'sender_authentication') {
                foreach (['spf', 'dkim', 'dmarc'] as $check) {
                    $dimensionIncomplete = $dimensionIncomplete || ! $selected->contains('check_key', $check);
                }
                if ($scan->selectors) {
                    $dimensionIncomplete = $dimensionIncomplete || $selected->where('check_key', 'dkim')->where('outcome', 'skipped')->isNotEmpty();
                }
            } elseif (! $nullMx) {
                $dimensionIncomplete = $dimensionIncomplete || $pending > 0 || $omitted > 0 || $endpoints->isEmpty() || $endpoints->whereIn('status', [EndpointStatus::Failed, EndpointStatus::Expired])->isNotEmpty();
                if ($key === 'transport' && ($scan->capabilities['relay_probe_configured'] ?? false)) {
                    $required[] = 'open_relay';
                }
                foreach ($endpoints as $endpoint) {
                    $rows = $selected->where('scan_endpoint_id', $endpoint->id);
                    foreach ($required as $check) {
                        $observation = $rows->firstWhere('check_key', $check);
                        $dimensionIncomplete = $dimensionIncomplete || ! $observation || in_array($observation->outcome, ['skipped', 'inconclusive'], true);
                    }
                }
            }
            if ($nullMx && $key === 'reachability') {
                $dimensionIncomplete = false;
            }
            $incomplete = $incomplete || $dimensionIncomplete;
            $dimensions[$key] = ['label' => $nullMx && $key === 'reachability' ? 'Not applicable' : $this->label($selected, $dimensionIncomplete), 'outcomes' => $selected->countBy('outcome')->all()];
        }

        $overall = $this->label($findings, $incomplete);
        if ($nullMx && $overall === 'Checks passed') {
            $overall = 'Mail intentionally disabled';
        }

        return ['overall' => $overall, 'dimensions' => $dimensions, 'coverage' => ['planned' => $endpoints->count(), 'completed' => $endpoints->where('status', EndpointStatus::Completed)->count(), 'pending' => $pending, 'failed' => $endpoints->whereIn('status', [EndpointStatus::Failed, EndpointStatus::Expired])->count(), 'omitted' => $omitted, 'incomplete' => $incomplete, 'ipv4' => $endpoints->where('family', 4)->count(), 'ipv6' => $endpoints->where('family', 6)->count(), 'not_run' => $findings->whereIn('outcome', ['skipped', 'inconclusive'])->count()]];
    }

    private function label(Collection $findings, bool $incomplete): string
    {
        if ($findings->contains(fn ($f) => $f->outcome === 'fail' || ($f->outcome === 'warning' && in_array($f->severity, ['critical', 'high'], true)))) {
            return 'Attention needed';
        }
        if ($incomplete) {
            return 'Incomplete';
        }
        if ($findings->contains(fn ($f) => $f->outcome === 'warning' && $f->severity === 'medium')) {
            return 'Improvements recommended';
        }

        return 'Checks passed';
    }
}
