<?php

namespace App\Mail\Value;

readonly class SmtpReply implements \JsonSerializable
{
    public function __construct(public int $code, public array $lines) {}

    public function accepted(): bool
    {
        return $this->code >= 200 && $this->code < 300;
    }

    public function capabilities(): array
    {
        return array_map(fn ($s) => strtoupper(explode(' ', trim($s))[0]), array_slice($this->lines, 1));
    }

    public function jsonSerialize(): array
    {
        return ['code' => $this->code, 'lines' => $this->lines];
    }
}
