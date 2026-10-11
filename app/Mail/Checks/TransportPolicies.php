<?php

namespace App\Mail\Checks;

use App\Mail\Dns\DnsLookup;
use App\Mail\Dns\TagRecordParser;
use App\Mail\Network\PinnedHttpsClient;
use App\Mail\Value\Finding;
use App\Mail\Value\PolicySnapshot;
use App\Mail\Value\RoutingSnapshot;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\ScanInput;

class TransportPolicies
{
    public function __construct(private DnsLookup $lookup, private TagRecordParser $parser, private PinnedHttpsClient $https, private MtaStsCheck $sts) {}

    public function discover(ScanInput $input, RoutingSnapshot $routing, ScanBudget $budget): PolicySnapshot
    {
        $findings = [];
        $policy = null;
        $tlsa = [];
        $advertisement = $this->lookup->resolve('_mta-sts.'.$input->domain, 'TXT', $budget);
        $records = array_values(array_filter($advertisement->texts(), fn ($t) => str_starts_with($t, 'v=STSv1;')));
        if (! $advertisement->ok() && $advertisement->status !== 'NXDOMAIN') {
            $policy = ['state' => 'error'];
            $findings[] = Finding::make('mtasts', 'inconclusive', 'MTA-STS lookup failed', $advertisement->status, $advertisement->jsonSerialize());
        } elseif (! $records) {
            $findings[] = Finding::make('mtasts', 'info', 'MTA-STS not published', 'An optional authenticated transport policy.', $advertisement->jsonSerialize());
        } else {
            $parsed = $this->parser->parse($records[0]);
            $valid = count($records) === 1 && ! $parsed['diagnostics'] && preg_match('/^[a-zA-Z0-9]{1,32}$/D', $parsed['tags']['id'] ?? '');
            if (! $valid) {
                $policy = ['state' => 'invalid'];
                $findings[] = Finding::make('mtasts', 'fail', 'Invalid MTA-STS advertisement', 'One valid version and policy id are required.', $advertisement->jsonSerialize(), 'Correct the _mta-sts TXT record.', 'medium');
            } else {
                $https = $this->https->get('mta-sts.'.$input->domain, '/.well-known/mta-sts.txt', $budget);
                if (! $https->success) {
                    $policy = ['state' => 'error'];
                    $findings[] = Finding::make('mtasts', 'warning', 'MTA-STS HTTPS policy unavailable', $https->error ?? 'Policy fetch failed.', ['https' => $https->jsonSerialize(), 'advertisement' => $advertisement->jsonSerialize()], 'Serve a valid policy over verified HTTPS without redirects.', 'medium');
                } else {
                    $policy = $this->sts->parse($https->body);
                    $policy['state'] = $policy['diagnostics'] ? 'invalid' : 'valid';
                    $policy['id'] = $parsed['tags']['id'];
                    $evidence = ['policy' => $policy, 'https' => $https->jsonSerialize(), 'advertisement' => $advertisement->jsonSerialize()];
                    $findings[] = Finding::make('mtasts', $policy['diagnostics'] ? 'fail' : ($policy['mode'] === 'enforce' ? 'pass' : 'info'), $policy['diagnostics'] ? 'Invalid MTA-STS policy' : 'MTA-STS policy retrieved', 'Each endpoint is assessed against the live policy; no sender cache is simulated.', $evidence, $policy['diagnostics'] ? 'Correct the policy file.' : null, $policy['diagnostics'] ? 'medium' : 'none');
                    if ($policy['duplicates']) {
                        $findings[] = Finding::make('mtasts_duplicates', 'warning', 'Duplicate MTA-STS fields', 'The first field value is used per RFC 8461.', ['duplicates' => $policy['duplicates']], 'Remove duplicate fields.', 'low');
                    }
                }
            }
        }
        $reporting = $this->lookup->resolve('_smtp._tls.'.$input->domain, 'TXT', $budget);
        $findings[] = Finding::make('tlsrpt', $reporting->ok() ? 'info' : 'inconclusive', $reporting->texts() ? 'TLS reporting record found' : 'No TLS reporting record', 'Supporting evidence for transport policy monitoring.', $reporting->jsonSerialize());
        foreach (array_unique(array_map(fn ($t) => $t->mx, $routing->targets)) as $mx) {
            $tlsa[$mx] = $this->lookup->resolve('_25._tcp.'.$mx,'TLSA',$budget);
        }

        return new PolicySnapshot($policy,$tlsa,$findings);
    }
}
