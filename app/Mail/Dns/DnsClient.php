<?php

namespace App\Mail\Dns;

use App\Mail\Value\DnsResult;
use App\Mail\Value\ScanBudget;

interface DnsClient
{
    public function query(string $name, string $type, ScanBudget $budget, bool $checkingDisabled = false): DnsResult;
}
