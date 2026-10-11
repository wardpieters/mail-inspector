<?php

namespace App\Mail\Value;

readonly class ProbeResult
{
    public function __construct(public array $findings, public bool $connected = false, public array $capabilities = [], public ?TlsObservation $tls = null, public array $transcript = []) {}
}
