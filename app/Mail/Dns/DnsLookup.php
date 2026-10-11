<?php

namespace App\Mail\Dns;

use App\Mail\Value\DnsResult;
use App\Mail\Value\ScanBudget;

class DnsLookup
{
    public function __construct(private DnsClient $dns) {}

    public function resolve(string $name, string $type, ScanBudget $budget): DnsResult
    {
        $original = $name;
        $seen = [];
        $secure = true;
        $aliases = [];
        for ($i = 0; $i < 8; $i++) {
            if (isset($seen[$name])) {
                return new DnsResult($original, $type, 'ERROR', [], 'indeterminate', 'CNAME cycle.', $aliases);
            }
            $seen[$name] = true;
            $result = $this->dns->query($name, $type, $budget);
            $secure = $secure && $result->validation === 'secure';
            if (! $result->ok()) {
                return new DnsResult($original, $type, $result->status, [], $result->validation, $result->error, $aliases);
            }
            $cnames = array_values(array_filter($result->ofType('CNAME'), fn ($r) => $r['name'] === $name));
            if ($cnames) {
                $name = $cnames[0]['data']['target'];
                $aliases[] = $name;

                continue;
            }
            $records = array_values(array_filter($result->ofType($type), fn ($r) => $r['name'] === $name));

            return new DnsResult($original, $type, 'NOERROR', $records, $secure ? 'secure' : 'insecure', null, $aliases);
        }

        return new DnsResult($original,$type,'ERROR',[],'indeterminate','CNAME hop limit reached.',$aliases);
    }
}
