<?php

namespace App\Mail\Dns;

use App\Mail\Value\DnsResult;
use App\Mail\Value\Finding;
use App\Mail\Value\ScanBudget;

class DnssecDiagnostics
{
    public function analyze(DnsResult $result, ScanBudget $budget): Finding
    {
        $budget->check();

        return match ($result->validation) {
            'secure' => Finding::make('dnssec', 'pass', 'DNSSEC validated', 'The controlled validating resolver authenticated this routing answer.', $result->jsonSerialize()),
            'bogus' => Finding::make('dnssec', 'fail', 'DNSSEC validation failed', 'The trusted resolver reported a bogus answer.', $result->jsonSerialize(), 'Repair DS/DNSKEY/signatures.', 'high'),
            'insecure' => Finding::make('dnssec', 'warning', 'Routing is not DNSSEC authenticated', 'The resolver did not authenticate this answer.', $result->jsonSerialize(), 'Sign the domain and publish its DS record.', 'low'),
            default => Finding::make('dnssec', 'inconclusive', 'DNSSEC could not be established', 'Resolver failure or untrusted resolver; signatures alone do not prove validation.', $result->jsonSerialize())
        };
    }
}
