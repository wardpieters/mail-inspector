<?php

namespace App\Mail\Checks;

use App\Mail\Dns\DnsLookup;
use App\Mail\Dns\SpfParser;
use App\Mail\Value\Finding;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\ScanInput;

class SpfCheck
{
    public function __construct(private DnsLookup $lookup, private SpfParser $parser) {}

    public function analyze(ScanInput $input, ScanBudget $budget): array
    {
        $state = ['path' => [], 'diagnostics' => [], 'limitations' => [], 'lookup_estimate' => 0, 'queries' => 0, 'dns_error' => false];
        $record = $this->walk($input->domain, $budget, [], $state);
        $outcome = 'pass';
        $severity = 'none';
        $title = 'SPF policy is configured';
        $recommendation = null;
        if ($state['dns_error']) {
            $outcome = 'inconclusive';
            $title = 'SPF lookup could not be completed';
        } elseif ($state['diagnostics']) {
            $outcome = 'fail';
            $severity = 'high';
            $title = 'SPF configuration errors';
            $recommendation = 'Repair the record and its include/redirect dependencies.';
        } elseif ($record === null) {
            $outcome = 'warning';
            $severity = 'medium';
            $title = 'No SPF policy found';
            $recommendation = 'Publish one SPF record covering authorized senders.';
        } else {
            $parsed = $this->parser->parse($record);
            $all = null;
            foreach ($parsed['mechanisms'] as $m) {
                if ($m['name'] === 'all') {
                    $all = $m['qualifier'];
                    break;
                }
            }
            if ($all === '+') {
                $outcome = 'fail';
                $severity = 'high';
                $title = 'SPF authorizes every sender';
                $recommendation = 'Replace +all with a restrictive policy after inventorying senders.';
            } elseif (in_array($all, ['~', '?'], true) || ($all === null && ! isset($parsed['modifiers']['redirect']))) {
                $outcome = 'warning';
                $severity = 'medium';
                $title = 'SPF policy is not strict';
                $recommendation = 'Consider -all once all legitimate senders are covered.';
            }
            if ($state['limitations'] && $outcome === 'pass') {
                $outcome = 'warning';
                $severity = 'low';
                $title = 'SPF needs runtime evaluation';
            }
        }

        return [Finding::make('spf', $outcome, $title, 'Checks publication and dependencies, not a message-level SPF result. Lookup totals are upper estimates for conditional paths.', $state, $recommendation, $severity)];
    }

    private function walk(string $domain, ScanBudget $budget, array $ancestors, array &$state): ?string
    {
        $budget->check();
        if (in_array($domain, $ancestors, true)) {
            $state['diagnostics'][] = 'Dependency cycle at '.$domain;

            return null;
        }
        if (count($ancestors) >= 10 || ++$state['queries'] > 40) {
            $state['limitations'][] = 'Dependency exploration limit reached.';

            return null;
        }
        $answer = $this->lookup->resolve($domain, 'TXT', $budget);
        $state['path'][] = $answer->jsonSerialize();
        if (! $answer->ok() && $answer->status !== 'NXDOMAIN') {
            $state['dns_error'] = true;

            return null;
        }
        $records = array_values(array_filter($answer->texts(), fn ($t) => preg_match('/^v=spf1(?: |$)/i', $t)));
        if (count($records) > 1) {
            $state['diagnostics'][] = 'Multiple SPF records at '.$domain;

            return null;
        }
        if (! $records) {
            if ($ancestors) {
                $state['diagnostics'][] = 'Missing SPF dependency at '.$domain;
            }

return null;
        }
        $record = $records[0];
        $parsed = $this->parser->parse($record);
        $state['diagnostics'] = array_merge($state['diagnostics'], $parsed['diagnostics']);
        $local = 0;
        $ancestors[] = $domain;
        foreach ($parsed['mechanisms'] as $m) {
            if ($m['name'] === 'all') {
                break;
            }
            if ($m['lookup']) {
                $local++;
                $state['lookup_estimate']++;
            }
            if ($m['macro']) {
                $state['limitations'][] = 'Dynamic macro at '.$domain;

                continue;
            }
            if ($m['name'] === 'ptr') {
                $state['limitations'][] = 'Deprecated ptr mechanism requires message context.';
            }
            if ($m['name'] === 'include') {
                $this->walk(strtolower(rtrim($m['value'], '.')), $budget, $ancestors, $state);
            }
        }
        if (isset($parsed['modifiers']['redirect']) && ! array_filter($parsed['mechanisms'], fn ($m) => $m['name'] === 'all')) {
            $local++;
            $state['lookup_estimate']++;
            $redirect = $parsed['modifiers']['redirect'];
            if (str_contains($redirect, '%')) {
                $state['limitations'][] = 'Dynamic redirect.';
            } else {
                $this->walk(strtolower(rtrim($redirect, '.')), $budget, $ancestors, $state);
            }
        }
        if ($local > 10) {
            $state['diagnostics'][] = 'A path through '.$domain.' can exceed the ten DNS-term limit.';
        }
        if ($state['lookup_estimate'] > 10 && $local <= 10) {
            $state['limitations'][] = 'Conditional dependency tree may exceed ten lookups; requires sender context.';
        }

        return $record;
    }
}
