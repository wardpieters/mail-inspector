<?php

namespace Tests\Unit;

use App\Mail\Exceptions\UnsafeTarget;
use App\Mail\Network\TargetGuard;
use Tests\TestCase;

class TargetGuardTest extends TestCase
{
    public function test_rejects_unsafe_target(): void
    {
        $guard = app(TargetGuard::class);
        foreach (['127.0.0.1', '10.0.0.1', '169.254.169.254', '::1', 'fe80::1', 'fd00::1', '2001:db8::1', '::ffff:10.0.0.1', '100.64.0.1'] as $ip) {
            try {
                $guard->assertPublic($ip);
                $this->fail('Accepted unsafe target '.$ip);
            } catch (UnsafeTarget $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_accepts_global_ipv4_and_ipv6(): void
    {
        $guard = app(TargetGuard::class);
        $guard->assertPublic('8.8.8.8');
        $guard->assertPublic('2606:4700:4700::1111');
        $this->assertSame('8.8.8.8', $guard->normalize('::ffff:8.8.8.8'));
    }
}
