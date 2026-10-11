<?php

namespace App\Mail\Dns;

use App\Mail\Value\ScanBudget;

class DmarcDiscovery
{
    public function __construct(private DnsLookup $lookup, private TagRecordParser $parser) {}

    public function discover(string $domain, ScanBudget $budget, bool $organizationalOnly = false): array
    {
        $path = [];
        $errors = [];
        $candidates = [];
        $dnsError = false;
        $name = $domain;
        $labels = explode('.', $domain);
        $org = $domain;
        for ($i = 0; $i < 8 && $name !== ''; $i++) {
            $answer = $this->lookup->resolve('_dmarc.'.$name, 'TXT', $budget);
            $path[] = $answer->jsonSerialize();
            if (! $answer->ok() && $answer->status !== 'NXDOMAIN') {
                $dnsError = true;
                break;
            }
            $records = array_values(array_filter($answer->texts(), fn ($text) => preg_match('/^v=DMARC1(?:\s*;|\s*$)/', $text)));
            if (count($records) > 1) {
                $errors[] = 'Multiple DMARC records at '.$name;
            } elseif (count($records) === 1) {
                $parsed = $this->parser->parse($records[0]);
                $tags = $parsed['tags'];
                $invalid = $parsed['diagnostics'];
                foreach (['adkim', 'aspf'] as $key) {
                    if (isset($tags[$key]) && ! in_array($tags[$key], ['r', 's'], true)) {
                        $invalid[] = 'Invalid '.$key;
                    }
                }
                if (isset($tags['psd']) && ! in_array($tags['psd'], ['y', 'n', 'u'], true)) {
                    $invalid[] = 'Invalid psd.';
                }
                $invalidPolicy = ! isset($tags['p']) || ! in_array($tags['p'], ['none', 'quarantine', 'reject'], true);
                foreach (['sp', 'np'] as $key) {
                    if (isset($tags[$key]) && ! in_array($tags[$key], ['none', 'quarantine', 'reject'], true)) {
                        $invalidPolicy = true;
                    }
                }
                if ($invalidPolicy) {
                    $errors[] = 'Invalid assessment policy at '.$name;
                    if (preg_match('/mailto:[^,@\s]+@[^,!\s]+/', $tags['rua'] ?? '')) {
                        $tags['p'] = 'none';
                        unset($tags['sp'],$tags['np']);
                        $tags['policy_fallback'] = 'none';
                    } else {
                        $invalid[] = 'Invalid policy without reporting fallback.';
                    }
                }
                if ($invalid) {
                    $errors = array_merge($errors, $invalid);
                } else {
                    $candidates[$name] = $tags;
                    if ($i === 0 && ! $organizationalOnly) {
                        return $this->result($domain, $name, $tags, $path, $errors, false, $domain, $budget);
                    }
                    if (($tags['psd'] ?? '') === 'n') {
                        $org = $name;
                        break;
                    }
                    if (($tags['psd'] ?? '') === 'y') {
                        $position = array_search($name, array_map(fn ($j) => implode('.', array_slice($labels, $j)), range(0, count($labels) - 1)), true);
                        $org = $position > 0 ? implode('.', array_slice($labels, $position - 1)) : $domain;
                        break;
                    }
                    $org = $name;
                }
            }
            $parts = explode('.', $name);
            $name = count($parts) >= 8 ? implode('.', array_slice($parts, -7)) : implode('.', array_slice($parts, 1));
        }
        $source = isset($candidates[$org]) ? $org : null;
        if (! $source) {
            foreach ($candidates as $candidate => $tags) {
                if (($tags['psd'] ?? '') === 'y') {
                    $source = $candidate;
                }
            }
        }

        return $this->result($domain, $source, $source ? $candidates[$source] : null, $path, $errors, $dnsError, $org, $budget);
    }

    private function result(string $domain, ?string $source, ?array $tags, array $path, array $errors, bool $dnsError, string $org, ScanBudget $budget): array
    {
        $policy = $tags['p'] ?? null;
        $existence = 'not_checked';
        if ($source && $source !== $domain) {
            $answer = $this->lookup->resolve($domain, 'A', $budget);
            $path[] = $answer->jsonSerialize();
            $existence = $answer->status === 'NXDOMAIN' ? 'nonexistent' : ($answer->ok() ? 'exists' : 'indeterminate');
            if ($existence === 'indeterminate') {
                $dnsError = true;
            } else {
                $policy = $existence === 'nonexistent' ? ($tags['np'] ?? $tags['p']) : ($tags['sp'] ?? $tags['p']);
            }
        }

        return ['source' => $source, 'policy' => $tags, 'effective_policy' => $policy, 'organizational_domain' => $org, 'existence' => $existence, 'path' => $path, 'diagnostics' => $errors, 'dns_error' => $dnsError];
    }
}
