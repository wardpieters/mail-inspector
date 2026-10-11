<?php

namespace App\Mail\Dns;

use App\Mail\Value\EndpointTarget;
use App\Mail\Value\Finding;
use App\Mail\Value\ScanBudget;

class ReverseDns
{
    public function __construct(private DnsLookup $lookup) {}

    public function analyze(EndpointTarget $target, ScanBudget $budget): Finding
    {
        $binary = inet_pton($target->ip);
        $name = strlen($binary) === 4 ? implode('.', array_reverse(explode('.', $target->ip))).'.in-addr.arpa' : implode('.', array_reverse(str_split(bin2hex($binary)))).'.ip6.arpa';
        $ptr = $this->lookup->resolve($name, 'PTR', $budget);
        $evidence = ['ptr' => $ptr->jsonSerialize(), 'forward' => []];
        if (! $ptr->ok() && $ptr->status !== 'NXDOMAIN') {
            return Finding::make('fcrdns', 'inconclusive', 'Reverse DNS lookup failed', $ptr->status, $evidence);
        }
        if (! $ptr->ofType()) {
            return Finding::make('fcrdns', 'warning', 'No reverse DNS name', 'Inbound service can work without a PTR, but naming is incomplete.', $evidence, 'Configure PTR with the address provider.', 'low');
        }
        $errors = false;
        foreach (array_slice($ptr->ofType(), 0, 8) as $record) {
            $forward = $this->lookup->resolve($record['data']['target'], $target->family === 4 ? 'A' : 'AAAA', $budget);
            $evidence['forward'][] = $forward->jsonSerialize();
            $errors = $errors || (! $forward->ok() && $forward->status !== 'NXDOMAIN');
            foreach ($forward->ofType() as $address) {
                if (@inet_pton($address['data']['address']) === $binary) {
                    return Finding::make('fcrdns', 'pass', 'Reverse DNS is forward confirmed', 'A PTR name resolves back to this exact address.', $evidence);
                }
            }
        }

        return Finding::make('fcrdns', $errors ? 'inconclusive' : 'warning', 'Reverse DNS is not forward confirmed', 'No tested PTR name resolved back to this address.', $evidence, 'Align PTR and forward address records.', $errors ? 'none' : 'low');
    }
}
