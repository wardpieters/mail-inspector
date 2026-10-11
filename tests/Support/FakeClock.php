<?php

namespace Tests\Support;

use App\Mail\Network\Clock;

class FakeClock implements Clock
{
    public float $time = 0;

    public function now(): float
    {
        return $this->time;
    }

    public function advance(float $seconds): void
    {
        $this->time += $seconds;
    }
}
