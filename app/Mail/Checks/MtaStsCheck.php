<?php

namespace App\Mail\Checks;

use App\Mail\Value\EndpointTarget;
use App\Mail\Value\Finding;
use App\Mail\Value\ProbeResult;

class MtaStsCheck
{
    public function parse(string $body): array
    {
        $fields = ['mx' => []];
        $errors = [];
        $duplicates = [];
        foreach (preg_split('/\r?\n/', trim($body)) as $line) {
            if (! preg_match('/^([a-zA-Z0-9_.-]{1,32}):[ \t]*(.*?)[ \t]*$/D', $line, $match)) {
                $errors[] = 'Malformed policy line.';

                continue;
            }
            $key = $match[1];
            if ($key === 'mx') {
                $fields['mx'][] = strtolower($match[2]);
            } elseif (array_key_exists($key, $fields)) {
                $duplicates[] = $key;
            } else {
                $fields[$key] = $match[2];
            }
        }
        if (($fields['version'] ?? null) !== 'STSv1') {
            $errors[] = 'Unsupported version.';
        }
        if (! in_array($fields['mode'] ?? null, ['none', 'testing', 'enforce'], true)) {
            $errors[] = 'Invalid mode.';
        }
        if (! preg_match('/^[0-9]{1,10}$/D', $fields['max_age'] ?? '') || (int) $fields['max_age'] > 31557600) {
            $errors[] = 'Invalid max_age.';
        }
        if (($fields['mode'] ?? null) !== 'none' && ! $fields['mx']) {
            $errors[] = 'Missing MX patterns.';
        }
        foreach ($fields['mx'] as $pattern) {
            $name = str_starts_with($pattern, '*.') ? substr($pattern, 2) : $pattern;
            if (strlen($name) > 253 || ! str_contains($name, '.')) {
                $errors[] = 'Invalid MX pattern.';
            }foreach (explode('.', $name) as $label) {
                if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label)) {
                    $errors[] = 'Invalid MX label.';
                }
            }
        }

        return $fields + ['diagnostics' => $errors, 'duplicates' => $duplicates];
    }

    public function matches(string $hostname, string $pattern): bool
    {
        $hostname = strtolower(rtrim($hostname, '.'));
        $pattern = strtolower($pattern);
        if (! str_starts_with($pattern, '*.')) {
            return $hostname === $pattern;
        }
        $suffix = substr($pattern, 1);

        return str_ends_with($hostname, $suffix) && ! str_contains(substr($hostname, 0, -strlen($suffix)), '.') && strlen($hostname) > strlen($suffix);
    }

    public function evaluate(EndpointTarget $target, ProbeResult $probe, ?array $policy): Finding
    {
        if (! $policy) {
            return Finding::make('mtasts', 'info', 'MTA-STS not published', 'No policy was discovered for this scan.');
        }
        if (($policy['state'] ?? 'valid') !== 'valid') {
            return Finding::make('mtasts', 'inconclusive', 'MTA-STS policy unavailable', 'Policy discovery did not produce a usable HTTPS policy.', $policy);
        }
        if ($policy['mode'] === 'none') {
            return Finding::make('mtasts', 'info', 'MTA-STS explicitly disabled', 'Mode none does not enforce authenticated transport.', $policy);
        }
        $covered = (bool) array_filter($policy['mx'], fn ($pattern) => $this->matches($target->mx, $pattern));
        $cert = array_values(array_filter($probe->findings, fn ($f) => $f->key === 'cert'))[0] ?? null;
        $pkix = $cert?->evidence['pkix']['valid'] ?? ($policy['pkix_valid'] ?? false);
        $san = $cert?->evidence['san'] ?? '';
        $sanMatches = false;
        foreach (explode(',', $san) as $value) {
            $value = trim($value);
            if (str_starts_with($value, 'DNS:') && $this->matches($target->mx, substr($value, 4))) {
                $sanMatches = true;
            }
        }
        $valid = $covered && ($probe->tls?->success ?? false) && $pkix && $sanMatches;

        return Finding::make('mtasts', $valid ? ($policy['mode'] === 'testing' ? 'warning' : 'pass') : ($policy['mode'] === 'enforce' ? 'fail' : 'warning'), $valid ? 'Endpoint satisfies MTA-STS' : 'Endpoint does not satisfy MTA-STS', 'MX coverage, successful STARTTLS, and PKIX with matching DNS SAN are required.', ['mode' => $policy['mode'], 'mx_covered' => $covered, 'pkix_valid' => $pkix, 'san_matches' => $sanMatches, 'tls' => $probe->tls?->success ?? false], $valid && $policy['mode'] === 'enforce' ? null : 'Align the MX pattern, STARTTLS certificate, and enforcement mode.', $valid ? ($policy['mode'] === 'testing' ? 'low' : 'none') : ($policy['mode'] === 'enforce' ? 'high' : 'medium'));
    }
}
