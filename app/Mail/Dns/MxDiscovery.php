<?php

namespace App\Mail\Dns;

use App\Mail\Exceptions\UnsafeTarget;
use App\Mail\Network\TargetGuard;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\Finding;
use App\Mail\Value\RoutingSnapshot;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\ScanInput;

class MxDiscovery
{
    public function __construct(private DnsLookup $lookup, private TargetGuard $guard, private DnssecDiagnostics $dnssec) {}

    public function discover(ScanInput $input, ScanBudget $budget): RoutingSnapshot
    {
        $mx = $this->lookup->resolve($input->domain, 'MX', $budget);
        $evidence = [$mx->jsonSerialize()];
        $findings = [$this->dnssec->analyze($mx, $budget)];
        $targets = [];
        $omitted = 0;
        if (! $mx->ok()) {
            $missing = $mx->status === 'NXDOMAIN';
            $findings[] = Finding::make('mx', $missing ? 'fail' : 'inconclusive', 'Mail routing could not be resolved', $mx->status, $mx->jsonSerialize(), $missing ? 'Correct the domain DNS configuration.' : 'Retry when the resolver is available.', $missing ? 'high' : 'none');

            return new RoutingSnapshot([], $findings, false, 0, $evidence);
        }
        $records = $mx->ofType();
        if (count($records) === 1 && $records[0]['data']['exchange'] === '.' && $records[0]['data']['preference'] === 0) {
            $findings[] = Finding::make('mx', 'pass', 'Domain explicitly declines mail', 'Valid null MX; SMTP checks are not applicable.', $mx->jsonSerialize());

            return new RoutingSnapshot([], $findings, true, 0, $evidence);
        }
        if (! $records) {
            $records = [['data' => ['exchange' => $input->domain, 'preference' => 0]]];
            $findings[] = Finding::make('mx', 'info', 'Implicit mail routing', 'No MX record; the domain address records are used.');
        } else {
            $findings[] = Finding::make('mx', 'pass', 'MX routing published', 'Every priority is inspected.', $mx->jsonSerialize());
        }
        if ($mx->aliases) {
            $findings[] = Finding::make('mx_alias', 'warning', 'Domain MX query follows an alias', 'Aliases in routing can reduce interoperability.', ['aliases' => $mx->aliases], 'Publish direct MX routing.', 'medium');
        }
        usort($records, fn ($a, $b) => $a['data']['preference'] <=> $b['data']['preference']);
        $seen = [];
        foreach ($records as $record) {
            $host = $record['data']['exchange'];
            $preference = $record['data']['preference'];
            if ($host === '.') {
                $findings[] = Finding::make('mx_invalid_null', 'fail', 'Invalid null MX combination', 'Null MX must be the sole record with priority 0.', [], 'Correct the MX records.', 'high', $host);

                continue;
            }
            $count = 0;
            $uncertain = false;
            foreach (['A' => 4, 'AAAA' => 6] as $type => $family) {
                $addresses = $this->lookup->resolve($host, $type, $budget);
                $evidence[] = $addresses->jsonSerialize();
                if (! $addresses->ok() && $addresses->status !== 'NXDOMAIN') {
                    $uncertain = true;
                    $findings[] = Finding::make('mx_address_'.$type, 'inconclusive', 'Address lookup failed', $addresses->status, $addresses->jsonSerialize(), null, 'none', $host);
                }
                if ($addresses->aliases) {
                    $findings[] = Finding::make('mx_alias_'.$type, 'warning', 'MX hostname is an alias', 'MX targets should resolve directly.', $addresses->jsonSerialize(), 'Use a canonical MX hostname.', 'medium', $host);
                }
                foreach ($addresses->ofType() as $address) {
                    try {
                        $ip = $this->guard->normalize($address['data']['address']);
                        $this->guard->assertPublic($ip);
                    } catch (UnsafeTarget $e) {
                        $uncertain = true;
                        $findings[] = Finding::make('mx_target_blocked', 'inconclusive', 'Unsafe mail target blocked', $e->getMessage(), ['ip' => $address['data']['address']], null, 'none', $host.':'.$address['data']['address']);

                        continue;
                    }
                    $count++;
                    $identity = $host.'|'.$ip;
                    if (isset($seen[$identity])) {
                        continue;
                    }$seen[$identity] = true;
                    if (count($targets) >= (int) config('mail_scan.max_endpoints', 64)) {
                        $omitted++;

                        continue;
                    }
                    $targets[] = new EndpointTarget($host, $preference, $ip, str_contains($ip, ':') ? 6 : 4, $mx->validation === 'secure' && $addresses->validation === 'secure');
                }
            }
            if ($count === 0) {
                $findings[] = Finding::make('mx_no_address', $uncertain ? 'inconclusive' : 'fail', $uncertain ? 'MX address discovery is incomplete' : 'MX has no usable public addresses', 'No endpoint can be probed.', [], $uncertain ? 'Retry address discovery after the lookup or scanner limitation is resolved.' : 'Publish usable A or AAAA records.', $uncertain ? 'none' : 'high', $host);
            }
        }
        if ($omitted) {
            $findings[] = Finding::make('coverage', 'inconclusive', 'Endpoint limit reached', "$omitted addresses were omitted.", ['omitted' => $omitted]);
        }

        return new RoutingSnapshot($targets, $findings, false, $omitted, $evidence);
    }
}
