<?php

namespace App\Mail\Network;

class SystemClock implements Clock
{
    public function now(): float
    {
        return hrtime(true) / 1e9;
    }
}
