<?php

namespace Tests\Integration;

use App\Mail\Network\ProbePermits;
use App\Mail\Value\EndpointTarget;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('integration')]
class ProbePermitsTest extends TestCase
{
    public function test_scan_and_alias_independent_ip_quotas(): void
    {
        if (! env('MAIL_TEST_INTEGRATION')) {
            $this->markTestSkipped('Requires isolated Redis test service.');
        }
        $permits = app(ProbePermits::class);
        $scan = uniqid('quota', true);
        $tokens = [];
        try {
            for ($i = 0; $i < 4; $i++) {
                $tokens[] = $permits->acquire($scan, new EndpointTarget('mx'.$i.'.test', 0, '8.8.8.'.($i + 1), 4));
                $this->assertNotNull($tokens[$i]);
            }
            $this->assertNull($permits->acquire($scan, new EndpointTarget('mx5.test', 0, '8.8.4.4', 4)));
            $permits->release('wrong-token');
            $this->assertNull($permits->acquire($scan, new EndpointTarget('mx5.test', 0, '8.8.4.4', 4)));
        } finally {
            foreach ($tokens as $token) {
                if ($token) {
                    $permits->release($token);
                }
            }
        }
        $first = $permits->acquire($scan, new EndpointTarget('alias1.test', 0, '8.8.8.8', 4));
        $second = $permits->acquire($scan, new EndpointTarget('alias2.test', 0, '8.8.8.8', 4));
        try {
            $this->assertNull($permits->acquire('another-scan', new EndpointTarget('alias3.test', 0, '8.8.8.8', 4)));
        } finally {
            $permits->release($first);
            $permits->release($second);
        }
    }
}
