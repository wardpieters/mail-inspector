<?php

namespace App\Mail\Certificates;

class CertificateAssociation
{
    public function usable(array $record): bool
    {
        return in_array($record['usage'] ?? null, [2, 3], true) && in_array($record['selector'] ?? null, [0, 1], true) && in_array($record['matching'] ?? null, [0, 1, 2], true) && ctype_xdigit($record['association'] ?? '') && strlen($record['association']) % 2 === 0 && match ($record['matching']) {
            1 => strlen($record['association']) === 64,2 => strlen($record['association']) === 128,0 => strlen($record['association']) >= 2
        };
    }

    public function matches(string $pem, array $record): bool
    {
        $data = $record['selector'] === 0 ? OpenSslVerifier::der($pem) : OpenSslVerifier::spki($pem);
        if ($data === '') {
            return false;
        }
        $association = match ($record['matching']) {
            0 => bin2hex($data),1 => hash('sha256', $data),2 => hash('sha512', $data)
        };

        return hash_equals(strtolower($record['association']), $association);
    }
}
