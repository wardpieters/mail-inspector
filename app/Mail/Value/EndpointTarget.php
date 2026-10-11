<?php

namespace App\Mail\Value;

readonly class EndpointTarget implements \JsonSerializable
{
    public function __construct(public string $mx, public int $preference, public string $ip, public int $family, public bool $discoverySecure = false) {}

    public function jsonSerialize(): array
    {
        return ['mx' => $this->mx, 'preference' => $this->preference, 'ip' => $this->ip, 'family' => $this->family, 'discovery_secure' => $this->discoverySecure];
    }

    public static function fromArray(array $row): self
    {
        return new self($row['mx'], (int) $row['preference'], $row['ip'], (int) $row['family'], (bool) ($row['discovery_secure'] ?? false));
    }
}
