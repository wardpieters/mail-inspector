<?php

namespace Tests\Support;

use App\Mail\Dns\DnsClient;
use App\Mail\Value\DnsResult;
use App\Mail\Value\ScanBudget;

class FakeDnsClient implements DnsClient
{
    private array $history = [];

    public function __construct(public array $responses = []) {}

    public static function fromFixture(string $path): self
    {
        return new self(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR));
    }

    public function queries(): array
    {
        return $this->history;
    }

    public function query(string $name, string $type, ScanBudget $budget, bool $checkingDisabled = false): DnsResult
    {
        $budget->check();
        $this->history[] = [$name, $type, $checkingDisabled];
        $key = strtolower(rtrim($name, '.')).' '.$type.($checkingDisabled ? ' CD' : '');
        $data = $this->responses[$key] ?? [];

        return new DnsResult($name, $type, $data['status'] ?? 'NOERROR', $data['records'] ?? [], $data['validation'] ?? 'insecure', $data['error'] ?? null);
    }

    public function add(string $name, string $type, array $data, array $records = [], string $validation = 'secure'): self
    {
        $this->responses[$name.' '.$type] = ['records' => $records ?: array_map(fn ($row) => ['name' => $name, 'type' => $type, 'ttl' => 300, 'data' => $row], $data), 'validation' => $validation];

        return $this;
    }
}
