<?php

namespace App\Mail\Value;

readonly class RoutingSnapshot implements \JsonSerializable
{
    public function __construct(public array $targets, public array $findings, public bool $nullMx = false, public int $omitted = 0, public array $dnsEvidence = []) {}

    public function jsonSerialize(): array
    {
        return ['targets' => array_map(fn ($t) => $t->jsonSerialize(), $this->targets), 'null_mx' => $this->nullMx, 'omitted' => $this->omitted, 'dns_evidence' => $this->dnsEvidence];
    }
}
