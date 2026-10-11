<?php

namespace App\Http\Resources;

use App\Mail\Scans\ReportAssessment;
use App\Mail\Smtp\TranscriptRedactor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScanReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scan = $this->resource;
        $scan->loadMissing(['findings', 'endpoints.findings']);
        $findings = fn ($rows) => $rows->sortBy(fn ($f) => array_search($f->severity, ['critical', 'high', 'medium', 'low', 'none'], true))->map(fn ($f) => ['key' => $f->check_key, 'scope' => preg_replace('/^endpoint:[0-9]+:/', '', $f->scope_key), 'outcome' => $f->outcome, 'severity' => $f->severity, 'title' => $f->title, 'explanation' => $f->explanation, 'recommendation' => $f->recommendation, 'evidence' => $this->redact($f->evidence), 'duration_ms' => $f->duration_ms])->values()->all();

        return ['scan' => ['public_id' => $scan->public_id, 'domain' => $scan->domain, 'selectors' => $scan->selectors, 'status' => $scan->status->value, 'created_at' => $scan->created_at->toIso8601String(), 'started_at' => $scan->started_at?->toIso8601String(), 'finished_at' => $scan->finished_at?->toIso8601String(), 'deadline' => $scan->deadline->toIso8601String(), 'expires_at' => $scan->created_at->copy()->addDays(config('mail_scan.retention_days', 7))->toIso8601String(), 'scanner_version' => $scan->scanner_version ?? config('mail_scan.version'), 'error' => $scan->terminal_error, 'null_mx' => (bool) ($scan->routing_snapshot['null_mx'] ?? false)], 'assessment' => app(ReportAssessment::class)->assess($scan), 'domain_findings' => $findings($scan->findings->whereNull('scan_endpoint_id')), 'endpoints' => $scan->endpoints->sortBy(fn ($e) => sprintf('%05d|%s|%d|%s', $e->preference, $e->mx, $e->family, $e->ip))->values()->map(fn ($e) => ['mx' => $e->mx, 'preference' => $e->preference, 'ip' => $e->ip, 'family' => $e->family, 'status' => $e->status->value, 'discovery_secure' => $e->discovery_secure, 'findings' => $findings($e->findings)])->all(), 'capabilities' => $scan->capabilities ?? [], 'export_url' => route('scans.export', $scan->public_id), 'home_url' => route('home'), 'limitations' => ['Observed from this scanner at the recorded time; a blocked outbound port 25 or missing IPv6 egress can affect results.', 'DKIM requires supplied selectors. SPF, DKIM, and DMARC checks inspect published configuration, not message authentication.', 'Relay probes stop at RCPT and RSET; no DATA or message body is sent. Recipient acceptance is only suspected relay behavior.', 'TLS cipher coverage is bounded and not exhaustive. Legacy crypto restrictions and missing DANE anchors are reported as limitations.', 'Reports are accessible to anyone holding their link and expire after '.config('mail_scan.retention_days', 7).' days.']];
    }

    private function redact(mixed $value): mixed
    {
        if (is_string($value)) {
            return app(TranscriptRedactor::class)->redact($value);
        }if (is_array($value)) {
            return array_map(fn ($v) => $this->redact($v), $value);
        }

return $value;
    }
}
