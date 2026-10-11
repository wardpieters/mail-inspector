<?php

namespace App\Mail\Value;

readonly class PolicySnapshot implements \JsonSerializable
{
    public function __construct(public ?array $mtaSts = null, public array $tlsaByMx = [], public array $findings = []) {}

    public function jsonSerialize(): array
    {
        return ['mta_sts' => $this->mtaSts, 'tlsa_by_mx' => array_map(fn ($answer) => $answer->jsonSerialize(), $this->tlsaByMx)];
    }

    public static function fromArray(array $values): self
    {
        return new self($values['mta_sts'] ?? null, array_map(fn ($v) => DnsResult::fromArray($v), $values['tlsa_by_mx'] ?? []));
    }
}
