<?php

namespace App\Mail\Network;

use App\Mail\Exceptions\UnsafeTarget;

class TargetGuard
{
    public function normalize(string $ip): string
    {
        $binary = @inet_pton($ip);
        if ($binary === false) {
            throw new UnsafeTarget('Invalid literal IP address.');
        }
        if (strlen($binary) === 16 && substr($binary, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $binary = substr($binary, 12);
        }

        return inet_ntop($binary);
    }

    public function assertPublic(string $ip): void
    {
        $ip = $this->normalize($ip);
        $binary = inet_pton($ip);
        $blocked = strlen($binary) === 4 ? ['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/3'] : ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20'];
        if (strlen($binary) === 16 && ! $this->inRange($binary, '2000::/3')) {
            throw new UnsafeTarget('Non-global IPv6 target blocked.');
        }
        foreach ($blocked as $range) {
            if ($this->inRange($binary, $range)) {
                throw new UnsafeTarget('Special-use target blocked.');
            }
        }
    }

    private function inRange(string $binary, string $cidr): bool
    {
        [$network,$bits] = explode('/', $cidr);
        $base = inet_pton($network);
        $bits = (int) $bits;
        if (strlen($binary) !== strlen($base)) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        $extra = $bits % 8;

        return substr($binary, 0, $bytes) === substr($base, 0, $bytes) && ($extra === 0 || ((ord($binary[$bytes]) ^ ord($base[$bytes])) & (255 << (8 - $extra))) === 0);
    }
}
