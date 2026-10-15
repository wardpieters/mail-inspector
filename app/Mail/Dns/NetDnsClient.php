<?php

namespace App\Mail\Dns;

use App\Mail\Value\DnsResult;
use App\Mail\Value\ScanBudget;
use NetDNS2\ENUM\Error;
use NetDNS2\Exception as DnsException;
use NetDNS2\Packet\Response;
use NetDNS2\Resolver;
use NetDNS2\RR;
use NetDNS2\RR\OPT\EDE;

class NetDnsClient implements DnsClient
{
    private int $queries = 0;

    public function query(string $name, string $type, ScanBudget $budget, bool $checkingDisabled = false): DnsResult
    {
        $budget->check();
        // NetDNS2 accepts literal resolver addresses; Docker config uses a service name.
        $server = (string) config('mail_scan.dns_server');
        $servers = filter_var($server, FILTER_VALIDATE_IP) ? [$server] : (gethostbynamel($server) ?: []);
        if ($servers === []) {
            return new DnsResult($name, $type, 'ERROR', [], 'indeterminate', 'Configured resolver hostname could not be resolved.');
        }
        $options = ['nameservers' => $servers, 'dns_port' => (int) config('mail_scan.dns_port'), 'dnssec' => true, 'dnssec_ad_flag' => true, 'dnssec_cd_flag' => $checkingDisabled, 'strict_query_mode' => false];
        foreach ([false, true] as $useTcp) {
            $budget->check();
            if (++$this->queries > 256) {
                return new DnsResult($name, $type, 'ERROR', [], 'indeterminate', 'DNS query budget exhausted.');
            }
            $options['use_tcp'] = $useTcp;
            /**
             * NetDNS2 can follow UDP write/read with TCP connect/write/read on truncation.
             * Share the remaining budget across those phases and all configured servers.
             */
            $options['timeout'] = min($useTcp ? 5 : 3, $budget->timeout(PHP_FLOAT_MAX) / (count($servers) * ($useTcp ? 3 : 5)));
            try {
                $packet = $this->queryResolver($name, $type, $options);
            } catch (DnsException $e) {
                $packet = $e->getResponse();
                if (! $packet) {
                    if (! $useTcp && $e->getCode() === Error::INT_FAILED_SOCKET->value) {
                        continue;
                    }

                    return new DnsResult($name, $type, 'ERROR', [], 'indeterminate', 'Resolver query failed: '.substr($e->getMessage(), 0, 300));
                }
            }

            break;
        }
        $records = [];
        foreach (array_slice($packet->answer, 0, 200) as $rr) {
            $record = $this->normalizeRecord($rr);
            if ($record) {
                $records[] = $record;
            }
        }
        $status = $packet->header->rcode->name;
        $validation = $checkingDisabled || ! config('mail_scan.dns_trusted') || $status === 'SERVFAIL' ? 'indeterminate' : ($packet->header->ad ? 'secure' : 'insecure');
        foreach ($packet->additional as $opt) {
            if ($opt instanceof EDE && in_array($opt->info_code, [6, 7, 8, 9, 10, 11, 12], true) && config('mail_scan.dns_trusted') && ! $checkingDisabled) {
                $validation = 'bogus';
            }
        }

        return new DnsResult($name, $type, $status, $records, $validation, $status === 'NOERROR' || $status === 'NXDOMAIN' ? null : $status);
    }

    /**
     * @param  array{nameservers: list<string>, dns_port: int, dnssec: bool, dnssec_ad_flag: bool, dnssec_cd_flag: bool, strict_query_mode: bool, use_tcp: bool, timeout: float}  $options
     */
    protected function queryResolver(string $name, string $type, array $options): Response
    {
        return (new Resolver($options))->query(rtrim($name, '.').'.', $type);
    }

    public function normalizeRecord(RR $rr): ?array
    {
        $type = $rr->type->label();
        $data = match ($type) {
            'A','AAAA' => ['address' => (string) $rr->address],
            'MX' => ['preference' => $rr->preference, 'exchange' => strtolower(rtrim((string) $rr->exchange, '.')) ?: '.'],
            'CNAME' => ['target' => strtolower(rtrim((string) $rr->cname, '.'))],
            'PTR' => ['target' => strtolower(rtrim((string) $rr->ptrdname, '.'))],
            'TXT' => ['text' => substr(implode('', array_map(fn ($t) => $t->value(), $rr->text)), 0, 8192)],
            'CAA' => ['flags' => $rr->flags, 'tag' => strtolower($rr->tag->value()), 'value' => $rr->value->value()],
            'TLSA' => ['usage' => $rr->cert_usage, 'selector' => $rr->selector, 'matching' => $rr->matching_type, 'association' => strtolower($rr->certificate)],
            default => null
        };

        return $data === null ? null : ['name' => strtolower(rtrim((string) $rr->name, '.')), 'type' => $type, 'ttl' => $rr->ttl, 'data' => $data];
    }
}
