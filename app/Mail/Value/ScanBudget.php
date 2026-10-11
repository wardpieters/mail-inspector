<?php

namespace App\Mail\Value;

use App\Mail\Exceptions\ScanDeadlineExceeded;
use App\Mail\Network\Clock;
use App\Mail\Network\SystemClock;

class ScanBudget
{
    private float $deadline;

    public function __construct(float $seconds, private Clock $clock = new SystemClock)
    {
        $this->deadline = $clock->now() + $seconds;
    }

    public function remaining(): float
    {
        return max(0, $this->deadline - $this->clock->now());
    }

    public function check(): void
    {
        if ($this->remaining() <= 0) {
            throw new ScanDeadlineExceeded('Scan time budget exhausted.');
        }
    }

    public function timeout(float $maximum): float
    {
        $this->check();

        return min($maximum, $this->remaining());
    }
}
