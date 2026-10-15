<?php

namespace Tests\Unit;

use App\Mail\Dns\NetDnsClient;
use App\Mail\Exceptions\ScanDeadlineExceeded;
use App\Mail\Value\ScanBudget;
use Closure;
use NetDNS2\Data\Text;
use NetDNS2\ENUM\Error;
use NetDNS2\ENUM\RR\Code;
use NetDNS2\Exception as DnsException;
use NetDNS2\Packet\Response;
use NetDNS2\RR;
use Tests\Support\FakeClock;
use Tests\TestCase;

class NetDnsClientTest extends TestCase
{
    public function test_recovers_mx_addresses_over_tcp_after_udp_timeout(): void
    {
        config(['mail_scan.dns_server' => '127.0.0.1', 'mail_scan.dns_port' => 1053, 'mail_scan.dns_trusted' => true]);
        $client = $this->clientWithTransport(function (array $options): Response {
            $this->assertSame(['127.0.0.1'], $options['nameservers']);
            $this->assertSame(1053, $options['dns_port']);
            $this->assertTrue($options['dnssec']);
            $this->assertTrue($options['dnssec_ad_flag']);
            $this->assertFalse($options['dnssec_cd_flag']);
            if (! ($options['use_tcp'] ?? false)) {
                throw new DnsException('timeout on read select()', Error::INT_FAILED_SOCKET);
            }

            $packet = $this->response();
            $packet->header->ad = 1;
            $packet->answer = [RR::fromString('mx.test. 60 IN A 93.184.216.34')];

            return $packet;
        });

        $result = $client->query('mx.test', 'A', new ScanBudget(30));

        $this->assertSame('NOERROR', $result->status);
        $this->assertSame('secure', $result->validation);
        $this->assertSame('93.184.216.34', $result->records[0]['data']['address']);
        $this->assertSame(2, $client->attempts);
    }

    public function test_persistent_transport_failures_stop_after_one_retry(): void
    {
        $client = $this->clientWithTransport(function (): Response {
            throw new DnsException('timeout on read select()', Error::INT_FAILED_SOCKET);
        });

        $result = $client->query('mx.test', 'AAAA', new ScanBudget(30));

        $this->assertSame('ERROR', $result->status);
        $this->assertSame('indeterminate', $result->validation);
        $this->assertSame([], $result->records);
        $this->assertStringContainsString('timeout on read select()', $result->error);
        $this->assertSame(2, $client->attempts);
    }

    public function test_dns_answers_and_invalid_queries_are_not_retried(): void
    {
        foreach ([Code::NOERROR, Code::NXDOMAIN, Code::SERVFAIL, Code::REFUSED, null] as $status) {
            $client = $this->clientWithTransport(function () use ($status): Response {
                if ($status === null) {
                    throw new DnsException('invalid type', Error::INT_INVALID_TYPE);
                }
                $packet = $this->response();
                $packet->header->rcode = $status;
                if ($status !== Code::NOERROR) {
                    throw new DnsException($status->name, Error::set($status->value), null, null, $packet);
                }

                return $packet;
            });

            $result = $client->query('mx.test', 'A', new ScanBudget(30));

            $this->assertSame($status?->name ?? 'ERROR', $result->status);
            $this->assertSame(1, $client->attempts);
        }
    }

    public function test_retry_preserves_checking_disabled_and_uses_remaining_budget(): void
    {
        $clock = new FakeClock;
        $client = $this->clientWithTransport(function (array $options) use ($clock): Response {
            $this->assertTrue($options['dnssec_cd_flag']);
            if (! ($options['use_tcp'] ?? false)) {
                $this->assertGreaterThan(1.5, $options['timeout']);
                $clock->advance(29);
                throw new DnsException('timeout on read select()', Error::INT_FAILED_SOCKET);
            }
            $this->assertLessThanOrEqual(1.0 / 3, $options['timeout']);

            return $this->response();
        });

        $result = $client->query('mx.test', 'AAAA', new ScanBudget(30, $clock), true);

        $this->assertSame('NOERROR', $result->status);
        $this->assertSame('indeterminate', $result->validation);
        $this->assertSame(2, $client->attempts);
    }

    public function test_reserves_remaining_time_for_automatic_tcp_fallback_on_truncation(): void
    {
        $client = $this->clientWithTransport(function (array $options): Response {
            $this->assertLessThanOrEqual(1.0, $options['timeout']);

            return $this->response();
        });

        $result = $client->query('mx.test', 'A', new ScanBudget(5, new FakeClock));

        $this->assertSame('NOERROR', $result->status);
    }

    public function test_does_not_retry_after_scan_deadline(): void
    {
        $clock = new FakeClock;
        $client = $this->clientWithTransport(function () use ($clock): Response {
            $clock->advance(5);
            throw new DnsException('timeout on read select()', Error::INT_FAILED_SOCKET);
        });

        $this->expectException(ScanDeadlineExceeded::class);
        try {
            $client->query('mx.test', 'A', new ScanBudget(5, $clock));
        } finally {
            $this->assertSame(1, $client->attempts);
        }
    }

    public function test_retries_count_toward_the_dns_query_limit(): void
    {
        $client = $this->clientWithTransport(function (): Response {
            throw new DnsException('timeout on read select()', Error::INT_FAILED_SOCKET);
        });
        $budget = new ScanBudget(30);
        for ($i = 0; $i < 128; $i++) {
            $client->query('mx.test', 'A', $budget);
        }

        $result = $client->query('mx.test', 'A', $budget);

        $this->assertSame('ERROR', $result->status);
        $this->assertSame('DNS query budget exhausted.', $result->error);
        $this->assertSame(256, $client->attempts);
    }

    private function response(): Response
    {
        return new Response(pack('nnnnnn', 1, 0x8180, 0, 0, 0, 0), 12);
    }

    private function clientWithTransport(Closure $transport): NetDnsClient
    {
        config(['mail_scan.dns_server' => '127.0.0.1']);

        return new class($transport) extends NetDnsClient
        {
            public int $attempts = 0;

            public function __construct(private Closure $transport) {}

            protected function queryResolver(string $name, string $type, array $options): Response
            {
                $this->attempts++;

                return ($this->transport)($options);
            }
        };
    }

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
