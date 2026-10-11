<?php

namespace Database\Factories;

use App\Models\Scan;
use App\Models\ScanEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScanEndpointFactory extends Factory
{
    protected $model = ScanEndpoint::class;

    public function definition(): array
    {
        return ['scan_id' => Scan::factory(), 'mx' => 'mx.test', 'preference' => 10, 'ip' => '8.8.8.8', 'family' => 4, 'discovery_secure' => true, 'status' => 'queued'];
    }
}
