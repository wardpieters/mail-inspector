<?php

namespace App\Mail\Checks;

use App\Mail\Dns\DnsLookup;
use App\Mail\Dns\TagRecordParser;
use App\Mail\Value\Finding;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\ScanInput;

class DkimCheck
{
    public function __construct(private DnsLookup $lookup, private TagRecordParser $parser) {}

    public function analyze(ScanInput $input, ScanBudget $budget): array
    {
        if (! $input->selectors) {
            return [Finding::make('dkim', 'skipped', 'DKIM selectors not supplied', 'Selectors cannot be discovered exhaustively. Enter selectors used by your sending service.')];
        }
        $findings = [];
        foreach ($input->selectors as $selector) {
            $result = $this->lookup->resolve($selector.'._domainkey.'.$input->domain, 'TXT', $budget);
            $evidence = ['dns' => $result->jsonSerialize(), 'selector' => $selector];
            $outcome = 'pass';
            $severity = 'none';
            $title = 'DKIM public key is published';
            $recommendation = null;
            if (! $result->ok() && $result->status !== 'NXDOMAIN') {
                $outcome = 'inconclusive';
                $title = 'DKIM lookup failed';
            } elseif (count($result->texts()) !== 1) {
                $outcome = 'fail';
                $title = count($result->texts()) > 1 ? 'Multiple DKIM key records' : 'DKIM key record missing';
                $severity = 'high';
            } else {
                $parsed = $this->parser->parse($result->texts()[0]);
                $tags = $parsed['tags'];
                $errors = $parsed['diagnostics'];
                $kind = strtolower($tags['k'] ?? 'rsa');
                if (isset($tags['v']) && ($tags['v'] !== 'DKIM1' || ($parsed['order'][0] ?? '') !== 'v')) {
                    $errors[] = 'Invalid or misplaced version.';
                }
                if (! array_key_exists('p', $tags)) {
                    $errors[] = 'Missing public key.';
                }
                if (isset($tags['h']) && ! in_array('sha256', explode(':', strtolower($tags['h'])), true)) {
                    $errors[] = 'No supported SHA-256 signature algorithm.';
                }
                if (isset($tags['s']) && ! array_intersect(['*', 'email'], explode(':', $tags['s']))) {
                    $errors[] = 'Key does not authorize email signing.';
                }
                $raw = base64_decode(preg_replace('/\s+/', '', $tags['p'] ?? ''), true);
                $evidence['algorithm'] = $kind;
                if (($tags['p'] ?? null) === '') {
                    $outcome = 'fail';
                    $title = 'DKIM selector is revoked';
                    $severity = 'medium';
                } elseif ($errors || $raw === false) {
                    $outcome = 'fail';
                    $title = 'Malformed DKIM key record';
                    $severity = 'high';
                } elseif (! in_array($kind, ['rsa', 'ed25519'], true)) {
                    $outcome = 'inconclusive';
                    $title = 'Unsupported DKIM key algorithm';
                } else {
                    $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($kind === 'ed25519' ? hex2bin('302a300506032b6570032100').$raw : $raw), 64, "\n")."-----END PUBLIC KEY-----\n";
                    $key = @openssl_pkey_get_public($pem);
                    $details = $key ? openssl_pkey_get_details($key) : false;
                    if (! $details || ($kind === 'rsa' && $details['type'] !== OPENSSL_KEYTYPE_RSA) || ($kind === 'ed25519' && (strlen($raw) !== 32 || $raw === str_repeat("\0", 32)))) {
                        $outcome = 'fail';
                        $title = 'Invalid DKIM public key';
                        $severity = 'high';
                    } else {
                        $evidence['key_bits'] = $details['bits'];
                        if ($kind === 'rsa' && $details['bits'] < 2048) {
                            $outcome = 'warning';
                            $title = 'DKIM RSA key is weak';
                            $severity = 'medium';
                        }
                    }
                    if (in_array('y', explode(':', $tags['t'] ?? ''), true) && $outcome === 'pass') {
                        $outcome = 'warning';
                        $title = 'DKIM key is in testing mode';
                        $severity = 'low';
                    }
                }
                $evidence['diagnostics'] = $errors;
            }
            if ($outcome === 'fail' || $outcome === 'warning') {
                $recommendation = 'Check this selector with the sending provider; publish a valid current key.';
            }
            $findings[] = Finding::make('dkim', $outcome, $title, 'Key publication and strength only; message signature verification requires a signed email.', $evidence, $recommendation, $severity, $selector);
        }

        return $findings;
    }
}
