<?php

namespace App\Mail\Checks;

use App\Mail\Dns\DnsLookup;
use App\Mail\Value\Finding;
use App\Mail\Value\ScanBudget;

class CaaCheck
{
    public function __construct(private DnsLookup $lookup) {}

    public function analyze(string $name, ScanBudget $budget): array
    {
        $subject = $name;
        $path = [];
        for ($i = 0; $i < 32 && $name !== ''; $i++) {
            $result = $this->lookup->resolve($name, 'CAA', $budget);
            $path[] = $result->jsonSerialize();
            if (! $result->ok() && $result->status !== 'NXDOMAIN') {
                return [Finding::make('caa', 'inconclusive', 'CAA discovery failed', $result->status, ['path' => $path], null, 'none', $subject)];
            }
            if ($result->ofType()) {
                $critical = array_filter($result->ofType(), fn ($r) => ($r['data']['flags'] & 128) !== 0 && ! in_array($r['data']['tag'], ['issue', 'issuewild', 'iodef'], true));

                return [Finding::make('caa', $critical ? 'warning' : 'pass', $critical ? 'Unrecognized critical CAA property' : 'Effective CAA policy found', 'CAA controls certificate issuance; it does not validate an existing SMTP certificate.', ['source' => $name, 'path' => $path], $critical ? 'Review the critical property; issuers must refuse issuance if they cannot process it.' : null, $critical ? 'medium' : 'none', $subject)];
            }
            $name = str_contains($name, '.') ? substr($name, strpos($name, '.') + 1) : '';
        }

        return [Finding::make('caa', 'info', 'No CAA issuance restriction', 'CAA is optional; absence does not invalidate certificates.', ['path' => $path], null, 'none', $subject)];
    }
}
