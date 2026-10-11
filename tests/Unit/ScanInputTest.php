<?php

namespace Tests\Unit;

use App\Mail\Value\ScanInput;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScanInputTest extends TestCase
{
    public function test_rejects_multiple_trailing_dots(): void
    {
        $this->expectException(ValidationException::class);
        ScanInput::fromRaw('example.com..');
    }
}
