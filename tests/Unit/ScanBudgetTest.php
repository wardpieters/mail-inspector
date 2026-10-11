<?php

namespace Tests\Unit;

use App\Mail\Exceptions\ScanDeadlineExceeded;
use App\Mail\Value\ScanBudget;
use Tests\Support\FakeClock;
use Tests\TestCase;

class ScanBudgetTest extends TestCase
{
    public function test_clamps_timeout_and_expires_at_deadline(): void
    {
        $clock = new FakeClock;
        $budget = new ScanBudget(300, $clock);
        $clock->advance(298);
        $this->assertSame(2.0, $budget->timeout(10));
        $clock->advance(2);
        $this->expectException(ScanDeadlineExceeded::class);
        $budget->check();
    }
}
