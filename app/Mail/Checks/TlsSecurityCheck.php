<?php

namespace App\Mail\Checks;

use App\Mail\Value\Finding;
use App\Mail\Value\TlsObservation;

class TlsSecurityCheck
{
    public function analyze(TlsObservation $negotiated, array $legacyAttempts): Finding
    {
        $evidence = ['negotiated' => $negotiated->jsonSerialize(), 'legacy_attempts' => $legacyAttempts, 'scope' => 'One negotiated cipher; not an exhaustive cipher inventory.'];
        if (! $negotiated->success) {
            return Finding::make('secure', 'skipped', 'TLS security could not be assessed', 'A successful handshake is required.', $evidence);
        }
        $modern = in_array($negotiated->protocol, ['TLSv1.2', 'TLSv1.3'], true) && $negotiated->bits >= 128 && ($negotiated->protocol === 'TLSv1.3' || preg_match('/^(?:ECDHE|DHE)-/', $negotiated->cipher));
        if (! $modern) {
            return Finding::make('secure', 'fail', 'Negotiated TLS is below the security baseline', 'Requires TLS 1.2/1.3, at least 128-bit encryption, and forward secrecy.', $evidence, 'Enable modern TLS and forward-secret cipher suites.', 'high');
        }
        if (array_filter($legacyAttempts, fn ($a) => $a['status'] === 'accepted')) {
            return Finding::make('secure', 'warning', 'Legacy TLS version accepted', 'A separate session successfully negotiated TLS 1.0 or 1.1.', $evidence, 'Disable TLS 1.0 and 1.1.', 'medium');
        }
        if (array_filter($legacyAttempts, fn ($a) => $a['status'] === 'inconclusive')) {
            return Finding::make('secure', 'inconclusive', 'Modern TLS negotiated; legacy coverage incomplete', 'The scanner could not establish rejection of every legacy version.', $evidence);
        }

        return Finding::make('secure', 'pass', 'Modern TLS security observed', 'The negotiated cipher meets the baseline; tested legacy versions were refused.', $evidence);
    }
}
