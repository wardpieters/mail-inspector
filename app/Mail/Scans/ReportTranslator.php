<?php

namespace App\Mail\Scans;

class ReportTranslator
{
    public function translate(array $report): array
    {
        $report['assessment']['overall'] = __($report['assessment']['overall'] === 'Incomplete' ? 'Assessment incomplete' : $report['assessment']['overall']);
        foreach ($report['assessment']['dimensions'] as &$dimension) {
            $dimension['label'] = __($dimension['label'] === 'Incomplete' ? 'Assessment incomplete' : $dimension['label']);
        }
        $report['scan']['error'] = $this->text($report['scan']['error']);
        $report['limitations'] = array_map($this->text(...), $report['limitations']);
        $report['domain_findings'] = array_map($this->finding(...), $report['domain_findings']);
        foreach ($report['endpoints'] as &$endpoint) {
            $endpoint['findings'] = array_map($this->finding(...), $endpoint['findings']);
        }

        return $report;
    }

    private function finding(array $finding): array
    {
        foreach (['title', 'explanation', 'recommendation'] as $field) {
            $finding[$field] = $this->text($finding[$field]);
        }

        return $finding;
    }

    private function text(?string $source): ?string
    {
        if ($source === null || $source === '') {
            return $source;
        }
        if (preg_match('/^Reports are accessible to anyone holding their link and expire after (\d+) days\.$/D', $source, $match)) {
            return __('Reports are accessible to anyone holding their link and expire after :days days.', ['days' => $match[1]]);
        }
        if (preg_match('/^(\d+) addresses were omitted\.$/D', $source, $match)) {
            return __(':count addresses were omitted.', ['count' => $match[1]]);
        }
        foreach (['TCP connection failed: ', 'Resolver query failed: ', 'No usable HTTPS address: '] as $prefix) {
            if (str_starts_with($source, $prefix)) {
                return __($prefix.':error', ['error' => __(substr($source, strlen($prefix)))]);
            }
        }

        return __($source);
    }
}
