<?php

namespace App\Mail\Checks;

use App\Mail\Certificates\CertificateAssociation;
use App\Mail\Certificates\OpenSslVerifier;
use App\Mail\Value\DnsResult;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\Finding;
use App\Mail\Value\ProbeResult;
use App\Mail\Value\ScanBudget;

class DaneCheck
{
    public function __construct(private CertificateAssociation $association, private OpenSslVerifier $verifier) {}

    public function evaluate(EndpointTarget $target, ProbeResult $probe, DnsResult $tlsa, ScanBudget $budget): Finding
    {
        $evidence = ['tlsa' => $tlsa->jsonSerialize(), 'routing_secure' => $target->discoverySecure];
        if ($tlsa->validation === 'bogus') {
            return Finding::make('dane', 'fail', 'TLSA DNSSEC validation failed', 'Bogus policy prevents authenticated DANE delivery.', $evidence, 'Repair TLSA DNSSEC validation.', 'high');
        }
        if (! $tlsa->ok() && $tlsa->status !== 'NXDOMAIN') {
            return Finding::make('dane', 'inconclusive', 'TLSA lookup failed', $tlsa->status, $evidence);
        }
        if (! $target->discoverySecure || $tlsa->validation !== 'secure') {
            return Finding::make('dane', 'info', 'DANE cannot authenticate this route', 'MX/address discovery and TLSA must all be DNSSEC authenticated.', $evidence);
        }
        if (! $tlsa->ofType()) {
            return Finding::make('dane', 'info', 'No SMTP TLSA policy', 'Authenticated absence of TLSA records.', $evidence);
        }
        $usable = array_values(array_filter($tlsa->ofType(), fn ($r) => $this->association->usable($r['data'])));
        $evidence['usable_records'] = count($usable);
        if (! $usable) {
            return Finding::make('dane', 'inconclusive', 'No supported usable TLSA association', 'SMTP DANE supports usages 2/3, selectors 0/1, and exact/SHA-256/SHA-512 matches.', $evidence);
        }
        if (! ($probe->tls?->success ?? false)) {
            return Finding::make('dane', 'fail', 'DANE requires successful TLS', 'An authenticated usable TLSA set requires TLS; this endpoint did not negotiate it.', $evidence, 'Enable working STARTTLS.', 'high');
        }
        $chain = $probe->tls->chainPem;
        $unavailable = false;
        $matchedAnchor = false;
        foreach ($usable as $rr) {
            $record = $rr['data'];
            $budget->check();
            if ($record['usage'] === 3) {
                if (isset($chain[0]) && $this->association->matches($chain[0], $record)) {
                    return Finding::make('dane', 'pass', 'DANE end-entity association verified', 'The leaf certificate matches authenticated TLSA; PKIX and expiry are independent diagnostics.', $evidence + ['matched' => $record]);
                }

                continue;
            }
            $anchors = [];
            foreach (array_slice($chain, 1) as $pem) {
                if ($this->association->matches($pem, $record)) {
                    $anchors[] = $pem;
                }
            }
            if ($record['selector'] === 0 && $record['matching'] === 0) {
                $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode(hex2bin($record['association'])), 64, "\n")."-----END CERTIFICATE-----\n";
                if (@openssl_x509_read($pem)) {
                    $anchors[] = $pem;
                }
            }
            if (! $anchors) {
                $unavailable = true;

                continue;
            }
            foreach (array_unique($anchors) as $anchor) {
                $matchedAnchor = true;
                $names = [$target->mx];
                foreach ($tlsa->aliases as $alias) {
                    if (str_starts_with($alias, '_25._tcp.')) {
                        $names[] = substr($alias, 9);
                    }
                }
                $verified = $this->verifier->verifyDaneAnchor($chain, $anchor, $names, $budget);
                $evidence['anchor_validation'][] = $verified;
                if ($verified['valid']) {
                    return Finding::make('dane', 'pass', 'DANE trust-anchor path verified', 'A matching anchor validates the certificate chain and reference identity.', $evidence + ['matched' => $record]);
                }
                if (! ($verified['available'] ?? true)) {
                    $unavailable = true;
                }
            }
        }
        if ($unavailable && ! $matchedAnchor) {
            return Finding::make('dane', 'inconclusive', 'DANE trust anchor unavailable', 'A hashed/SPKI anchor not supplied in the server chain cannot be reconstructed.', $evidence);
        }

        return Finding::make('dane', 'fail', 'No authenticated TLSA association validates this endpoint', 'Association matching and usage-specific certificate checks did not succeed.', $evidence, 'Update TLSA after certificate/key changes and include the required chain.', 'high');
    }
}
