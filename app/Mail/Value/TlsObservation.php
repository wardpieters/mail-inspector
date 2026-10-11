<?php

namespace App\Mail\Value;

readonly class TlsObservation implements \JsonSerializable
{
    public function __construct(public bool $success, public string $protocol = '', public string $cipher = '', public int $bits = 0, public array $chainPem = [], public ?string $error = null, public bool $clientCanOffer = true) {}

    public function jsonSerialize(): array
    {
        return ['success' => $this->success, 'protocol' => $this->protocol, 'cipher' => $this->cipher, 'bits' => $this->bits, 'error' => $this->error, 'client_can_offer' => $this->clientCanOffer];
    }
}
