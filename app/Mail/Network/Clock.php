<?php

namespace App\Mail\Network;

interface Clock
{
    public function now(): float;
}
