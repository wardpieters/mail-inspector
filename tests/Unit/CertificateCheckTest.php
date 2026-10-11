<?php

namespace Tests\Unit;

use App\Mail\Checks\CertificateCheck;
use App\Mail\Enums\Outcome;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ScanBudget;
use Tests\Support\CertificateFixtures;
use Tests\TestCase;

class CertificateCheckTest extends TestCase
{
    public function test_verifies_trust_and_hostname_independently_of_encryption(): void
    {
        $target = new EndpointTarget('mx.test', 0, '8.8.8.8', 4);
        foreach (['trusted' => Outcome::Pass, 'wrong-name' => Outcome::Fail, 'self-signed' => Outcome::Fail, 'expires-soon' => Outcome::Warning, 'weak' => Outcome::Warning] as $scenario => $outcome) {
            $chain = CertificateFixtures::chain($scenario);
            config(['mail_scan.ca_file' => CertificateFixtures::caFile()]);
            $this->assertSame($outcome, app(CertificateCheck::class)->analyze($target, $chain, new ScanBudget(30))->outcome, $scenario);
        }
    }

    public function test_expired_certificate_fails(): void
    {
        $chain = CertificateFixtures::chain('trusted');
        config(['mail_scan.ca_file' => CertificateFixtures::caFile()]);
        $this->travel(400)->days();
        $this->assertSame(Outcome::Fail, app(CertificateCheck::class)->analyze(new EndpointTarget('mx.test', 0, '8.8.8.8', 4), $chain, new ScanBudget(30))->outcome);
    }
}
