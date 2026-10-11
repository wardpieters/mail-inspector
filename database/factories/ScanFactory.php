<?php

namespace Database\Factories;

use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ScanFactory extends Factory
{
    protected $model = Scan::class;

    public function definition(): array
    {
        return ['public_id' => (string) Str::uuid(), 'domain' => 'fixture.test', 'selectors' => [], 'status' => 'queued', 'deadline' => now()->addMinutes(5)];
    }

    public function running(): static
    {
        return $this->state(fn () => ['status' => 'running', 'started_at' => now(), 'routing_snapshot' => ['null_mx' => false, 'omitted' => 0], 'policy_snapshot' => []]);
    }

    public function partial(): static
    {
        return $this->running()->state(fn () => ['status' => 'partial', 'finished_at' => now()]);
    }
}
