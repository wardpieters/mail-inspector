<?php

namespace App\Mail\Checks;

use App\Mail\Certificates\OpenSslVerifier;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\Finding;
use App\Mail\Value\ScanBudget;

class CertificateCheck
{
    public function __construct(private OpenSslVerifier $verifier) {}

    public function analyze(EndpointTarget $target, array $chainPem, ScanBudget $budget): Finding
    {
        if (! $chainPem) {
            return Finding::make('cert', 'skipped', 'No TLS certificate available', 'A TLS handshake is required.');
        }
        $cert = @openssl_x509_parse($chainPem[0]);
        $key = @openssl_pkey_get_public($chainPem[0]);
        $details = $key ? openssl_pkey_get_details($key) : false;
        if (! $cert || ! $details) {
            return Finding::make('cert', 'fail', 'Certificate could not be parsed', 'Invalid certificate data.', [], 'Install a valid server certificate.', 'high');
        }
        $verified = $this->verifier->verifyPkix($chainPem, $target->mx, $budget);
        $days = (int) floor(($cert['validTo_time_t'] - now()->timestamp) / 86400);
        $weak = ($details['type'] === OPENSSL_KEYTYPE_RSA && $details['bits'] < 2048) || ($details['type'] === OPENSSL_KEYTYPE_EC && $details['bits'] < 256);
        $evidence = ['pkix' => $verified, 'hostname' => $target->mx, 'subject' => $cert['subject'], 'issuer' => $cert['issuer'], 'san' => $cert['extensions']['subjectAltName'] ?? null, 'not_before' => gmdate('c', $cert['validFrom_time_t']), 'not_after' => gmdate('c', $cert['validTo_time_t']), 'days_remaining' => $days, 'key_bits' => $details['bits'], 'sha256' => openssl_x509_fingerprint($chainPem[0], 'sha256'), 'chain_length' => count($chainPem)];
        $outcome = (! $verified['available'] ? 'inconclusive' : (! $verified['valid'] ? 'fail' : ($weak || $days < 30 ? 'warning' : 'pass')));
        $severity = $outcome === 'fail' ? 'high' : ($outcome === 'warning' ? 'medium' : 'none');

        return Finding::make('cert', $outcome, $outcome === 'pass' ? 'Certificate trust and MX identity verified' : ($outcome === 'warning' ? 'Certificate needs renewal or stronger keys' : 'Certificate trust or identity verification failed'), 'PKIX trust, validity dates, and the MX hostname are verified independently of encryption.', $evidence, in_array($outcome, ['fail', 'warning'], true) ? 'Install a trusted certificate covering the MX hostname, include intermediates, and renew before expiry.' : null, $severity);
    }
}
