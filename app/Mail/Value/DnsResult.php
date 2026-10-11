<?php

namespace App\Mail\Value;

readonly class DnsResult implements \JsonSerializable
{
    public function __construct(public string $name, public string $type, public string $status = 'NOERROR', public array $records = [], public string $validation = 'indeterminate', public ?string $error = null, public array $aliases = []) {}

    public function ok(): bool
    {
        return $this->status === 'NOERROR';
    }

    public function ofType(?string $type = null): array
    {
        return array_values(array_filter($this->records, fn ($r) => $r['type'] === ($type ?? $this->type)));
    }

    public function texts(): array
    {
        return array_map(fn ($r) => $r['data']['text'], $this->ofType('TXT'));
    }

    public function jsonSerialize(): array
    {
        return ['name' => $this->name, 'type' => $this->type, 'status' => $this->status, 'validation' => $this->validation, 'records' => $this->records, 'error' => $this->error, 'aliases' => $this->aliases];
    }

    public static function fromArray(array $v): self
    {
        return new self($v['name'], $v['type'], $v['status'], $v['records'], $v['validation'], $v['error'] ?? null, $v['aliases'] ?? []);
    }
}
