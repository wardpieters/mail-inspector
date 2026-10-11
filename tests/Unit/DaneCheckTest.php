<?php

namespace Tests\Unit;

use App\Mail\Certificates\OpenSslVerifier;
use App\Mail\Checks\CertificateCheck;
use App\Mail\Checks\DaneCheck;
use App\Mail\Enums\Outcome;
use App\Mail\Value\DnsResult;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ProbeResult;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\TlsObservation;
use Tests\Support\CertificateFixtures;
use Tests\TestCase;

class DaneCheckTest extends TestCase
{
    public function test_usage_three_self_signed_is_independent_of_pkix_for_all_selectors_and_matches(): void
    {
        $chain = CertificateFixtures::chain('self-signed');
        $target = new EndpointTarget('mx.test', 0, '8.8.8.8', 4, true);
        $probe = new ProbeResult([], true, [], new TlsObservation(true, 'TLSv1.3', 'x', 256, $chain));
        $this->assertSame(Outcome::Fail, app(CertificateCheck::class)->analyze($target, $chain, new ScanBudget(30))->outcome);
        foreach ([0, 1] as $selector) {
            foreach ([0, 1, 2] as $matching) {
                $data = $selector === 0 ? OpenSslVerifier::der($chain[0]) : OpenSslVerifier::spki($chain[0]);
                $association = $matching === 0 ? bin2hex($data) : hash($matching === 1 ? 'sha256' : 'sha512', $data);
                $answer = new DnsResult('_25._tcp.mx.test', 'TLSA', 'NOERROR', [['name' => '_25._tcp.mx.test', 'type' => 'TLSA', 'ttl' => 300, 'data' => ['usage' => 3, 'selector' => $selector, 'matching' => $matching, 'association' => $association]]], 'secure');
                $this->assertSame(Outcome::Pass, app(DaneCheck::class)->evaluate($target, $probe, $answer, new ScanBudget(30))->outcome);
            }
        }
    }

    public function test_usage_two_hash_match_without_valid_anchored_chain_cannot_pass(): void
    {
        $chain = CertificateFixtures::chain('self-signed');
        $target = new EndpointTarget('mx.test', 0, '8.8.8.8', 4, true);
        $probe = new ProbeResult([], true, [], new TlsObservation(true, 'TLSv1.3', 'x', 256, $chain));
        $answer = new DnsResult('_25._tcp.mx.test', 'TLSA', 'NOERROR', [['name' => '_25._tcp.mx.test', 'type' => 'TLSA', 'ttl' => 300, 'data' => ['usage' => 2, 'selector' => 0, 'matching' => 1, 'association' => hash('sha256', OpenSslVerifier::der($chain[1]))]]], 'secure');
        $this->assertNotSame(Outcome::Pass,app(DaneCheck::class)->evaluate($target,$probe,$answer,new ScanBudget(30))->outcome);
    }
}
