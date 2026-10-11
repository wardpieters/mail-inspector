<?php

namespace App\Mail\Checks;

use App\Mail\Dns\ReverseDns;
use App\Mail\Enums\Outcome;
use App\Mail\Smtp\LegacyTlsProbe;
use App\Mail\Value\DnsResult;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\Finding;
use App\Mail\Value\PolicySnapshot;
use App\Mail\Value\ProbeResult;
use App\Mail\Value\ScanBudget;

class EndpointInspector
{
    public function __construct(private SmtpProbe $smtp, private CertificateCheck $certificates, private TlsSecurityCheck $security, private LegacyTlsProbe $legacy, private ReverseDns $reverse, private MtaStsCheck $sts, private DaneCheck $dane) {}

    public function inspect(EndpointTarget $target, PolicySnapshot $policies, ScanBudget $budget, ?string $domain = null): array
    {
        $probe = $this->smtp->probe($target, $budget, $domain);
        $findings = $probe->findings;
        try {
            $findings[] = $this->certificates->analyze($target, $probe->tls?->chainPem ?? [], $budget);
            $attempts = $probe->tls?->success ? $this->legacy->probe($target, $budget) : [];
            $findings[] = $probe->tls ? $this->security->analyze($probe->tls, $attempts) : Finding::make('secure', 'skipped', 'No TLS security observation', 'A TLS handshake is required.');
            $evaluated = new ProbeResult($findings, $probe->connected, $probe->capabilities, $probe->tls, $probe->transcript);
            $findings[] = $this->sts->evaluate($target, $evaluated, $policies->mtaSts);
            $findings[] = $this->dane->evaluate($target, $evaluated, $policies->tlsaByMx[$target->mx] ?? new DnsResult('_25._tcp.'.$target->mx, 'TLSA', 'ERROR'), $budget);
            $findings[] = $this->reverse->analyze($target, $budget);
        } catch (\Throwable $e) {
            $findings[] = Finding::make('endpoint_budget', 'inconclusive', 'Endpoint checks did not all complete', substr($e->getMessage(), 0, 300));
        }
        $keys = array_column(array_map(fn ($f) => $f->jsonSerialize(), $findings), 'key');
        foreach (['cert', 'secure', 'mtasts', 'dane', 'fcrdns'] as $key) {
            if (! in_array($key, $keys, true)) {
                $findings[] = Finding::make($key, 'skipped', 'Check did not complete', 'The endpoint budget or a prerequisite was exhausted.');
            }
        }
        $authenticated = (bool) array_filter($findings, fn ($f) => in_array($f->key, ['mtasts', 'dane'], true) && $f->outcome === Outcome::Pass);
        if (! $authenticated) {
            $findings[] = Finding::make('authenticated_transport', 'warning', 'Authenticated transport not established', 'Neither enforcing MTA-STS nor DANE was verified for this address.', [], 'Deploy MTA-STS enforcement or DNSSEC-authenticated DANE.', 'medium');
        }
        $findings[] = Finding::make('smtp_transcript', 'info', 'Bounded SMTP transcript', 'Configured envelope identities are redacted; no DATA command is sent.', ['transcript' => $probe->transcript]);

        return $findings;
    }
}
