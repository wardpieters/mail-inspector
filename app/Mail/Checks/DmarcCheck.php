<?php

namespace App\Mail\Checks;

use App\Mail\Dns\DmarcDiscovery;
use App\Mail\Dns\DnsLookup;
use App\Mail\Dns\TagRecordParser;
use App\Mail\Value\Finding;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\ScanInput;

class DmarcCheck
{
    public function __construct(private DmarcDiscovery $discovery, private DnsLookup $lookup, private TagRecordParser $parser) {}

    public function analyze(ScanInput $input, ScanBudget $budget): array
    {
        $result = $this->discovery->discover($input->domain, $budget);
        $policy = $result['policy'];
        $outcome = 'pass';
        $severity = 'none';
        $title = 'DMARC policy is configured';
        if ($result['dns_error']) {
            $outcome = 'inconclusive';
            $title = 'DMARC discovery could not be completed';
        } elseif ($result['diagnostics']) {
            $outcome = 'fail';
            $severity = 'high';
            $title = 'DMARC configuration errors';
        } elseif (! $policy) {
            $outcome = 'warning';
            $severity = 'medium';
            $title = 'No applicable DMARC policy';
        } elseif ($result['effective_policy'] === 'none') {
            $outcome = 'warning';
            $severity = 'medium';
            $title = 'DMARC is in monitoring mode';
        }
        $findings = [Finding::make('dmarc', $outcome, $title, 'RFC 9989 policy discovery; alignment and authentication need an actual email.', $result, in_array($outcome, ['fail', 'warning'], true) ? 'Review DMARC policy and alignment of legitimate senders.' : null, $severity)];
        if (! $policy) {
            return $findings;
        }
        $legacy = array_intersect_key($policy, array_flip(['pct', 'rf', 'ri', 'fo']));
        if ($legacy) {
            $findings[] = Finding::make('dmarc_legacy', 'info', 'Legacy DMARC metadata', 'Some receivers still use these historical tags; current policy discovery does not depend on them.', ['tags' => $legacy]);
        }
        $sourceOrg = $this->discovery->discover($result['source'], $budget, true);
        foreach (array_slice(explode(',', $policy['rua'] ?? ''), 0, 10) as $uri) {
            $uri = trim($uri);
            if ($uri === '') {
                continue;
            }
            if (! preg_match('/^mailto:([^@\s]+)@([a-z0-9.-]+)(?:![0-9]+[kmgt]?)?$/i', $uri, $match)) {
                $findings[] = Finding::make('dmarc_reporting', 'warning', 'Unsupported or invalid reporting URI', 'Only mailto reporting destinations are inspected.', ['uri' => $uri], 'Correct the reporting URI.', 'low', $uri);

                continue;
            }
            $host = strtolower($match[2]);
            $destinationOrg = $this->discovery->discover($host, $budget, true);
            if (! $sourceOrg['dns_error'] && ! $destinationOrg['dns_error'] && $sourceOrg['organizational_domain'] === $destinationOrg['organizational_domain']) {
                continue;
            }
            $name = $result['source'].'._report._dmarc.'.$host;
            if (strlen($name) > 253) {
                $findings[] = Finding::make('dmarc_reporting', 'warning', 'Reporting authorization name is too long', 'DNS cannot represent this authorization name.', ['uri' => $uri], null, 'low', $host);

                continue;
            }
            $answer = $this->lookup->resolve($name, 'TXT', $budget);
            $authorized = false;
            foreach ($answer->texts() as $text) {
                $parsed = $this->parser->parse($text);
                if (! $parsed['diagnostics'] && ($parsed['order'][0] ?? '') === 'v' && ($parsed['tags']['v'] ?? '') === 'DMARC1') {
                    $override = $parsed['tags']['rua'] ?? null;
                    $validOverride = true;
                    if ($override) {
                        foreach (explode(',', $override) as $destination) {
                            if (! preg_match('/^mailto:[^@\s]+@'.preg_quote($host, '/').'(?:![0-9]+[kmgt]?)?$/i', trim($destination))) {
                                $validOverride = false;
                            }
                        }
                    }
                    $authorized = $authorized || $validOverride;
                }
            }
            $findings[] = Finding::make('dmarc_reporting', ! $answer->ok() && $answer->status !== 'NXDOMAIN' ? 'inconclusive' : ($authorized ? 'pass' : 'warning'), $authorized ? 'External reporting destination authorized' : 'External reporting authorization missing', 'Receivers may ignore an unauthorized external reporting destination.', ['uri' => $uri, 'dns' => $answer->jsonSerialize()], $authorized ? null : 'Publish the authorization record at the report provider.', $authorized ? 'none' : 'low', $host);
        }

        return $findings;
    }
}
