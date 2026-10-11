<?php

namespace Tests\Unit;

use App\Mail\Dns\DnsClient;
use App\Mail\Network\PinnedHttpsClient;
use App\Mail\Value\ScanBudget;
use Tests\Support\FakeDnsClient;
use Tests\TestCase;

class PinnedHttpsClientTest extends TestCase
{
    public function test_private_destination_is_blocked_before_https(): void
    {
        $dns = new FakeDnsClient;
        $dns->add('mta-sts.fixture.test', 'A', [['address' => '127.0.0.1']]);
        $this->app->instance(DnsClient::class, $dns);
        $this->assertFalse(app(PinnedHttpsClient::class)->get('mta-sts.fixture.test', '/.well-known/mta-sts.txt', new ScanBudget(30))->success);
    }
}
