<?php

namespace Tests\Unit;

use App\Mail\Dns\NetDnsClient;
use NetDNS2\Data\Text;
use NetDNS2\RR;
use Tests\TestCase;

class NetDnsClientTest extends TestCase
{
    public function test_normalizes_chunked_txt_tlsa_and_null_mx(): void
    {
        $client = new NetDnsClient;
        $record = RR::fromString('fixture.test. 60 IN TXT "placeholder"');
        $record->text = [new Text('v=spf1 '), new Text('-all')];
        $txt = $client->normalizeRecord($record);
        $this->assertSame('v=spf1 -all', $txt['data']['text']);
        $mx = $client->normalizeRecord(RR::fromString('fixture.test. 60 IN MX 0 .'));
        $this->assertSame('.', $mx['data']['exchange']);
        $tlsa = $client->normalizeRecord(RR::fromString('_25._tcp.mx.test. 60 IN TLSA 3 1 1 '.str_repeat('ab', 32)));
        $this->assertSame(3, $tlsa['data']['usage']);
        $this->assertSame(str_repeat('ab', 32), $tlsa['data']['association']);
    }
}
