<?php

namespace Tests\Unit;

use App\Mail\Checks\TlsSecurityCheck;
use App\Mail\Enums\Outcome;
use App\Mail\Value\TlsObservation;
use Tests\TestCase;

class TlsSecurityCheckTest extends TestCase
{
    public function test_legacy_acceptance_warns_and_local_inability_is_inconclusive(): void
    {
        $modern = new TlsObservation(true, 'TLSv1.3', 'TLS_AES_256_GCM_SHA384', 256);
        $this->assertSame(Outcome::Warning, app(TlsSecurityCheck::class)->analyze($modern, [['version' => 'TLSv1', 'status' => 'accepted']])->outcome);
        $this->assertSame(Outcome::Inconclusive, app(TlsSecurityCheck::class)->analyze($modern, [['version' => 'TLSv1', 'status' => 'inconclusive']])->outcome);
    }

    public function test_weak_cipher_fails(): void
    {
        $this->assertSame(Outcome::Fail, app(TlsSecurityCheck::class)->analyze(new TlsObservation(true, 'TLSv1.2', 'AES128-SHA', 128), [])->outcome);
    }
}
