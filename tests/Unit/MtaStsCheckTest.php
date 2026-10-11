<?php

namespace Tests\Unit;

use App\Mail\Checks\MtaStsCheck;
use App\Mail\Enums\Outcome;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ProbeResult;
use App\Mail\Value\TlsObservation;
use Tests\TestCase;

class MtaStsCheckTest extends TestCase
{
    public function test_wildcard_matches_exactly_one_label(): void
    {
        $check = app(MtaStsCheck::class);
        $this->assertTrue($check->matches('mail.example.test', '*.example.test'));
        $this->assertFalse($check->matches('example.test', '*.example.test'));
        $this->assertFalse($check->matches('a.mail.example.test', '*.example.test'));
    }

    public function test_duplicate_fields_use_first_value_and_mode_none_does_not_require_mx(): void
    {
        $check = app(MtaStsCheck::class);
        $this->assertEmpty($check->parse("version: STSv1\nmode: enforce\nmode: testing\nmax_age: 300\nmx: mx.test\n")['diagnostics']);
        $this->assertSame('enforce', $check->parse("version: STSv1\nmode: enforce\nmode: testing\nmax_age: 300\nmx: mx.test\n")['mode']);
        $this->assertEmpty($check->parse("version: STSv1\nmode: none\nmax_age: 0\n")['diagnostics']);
    }

    public function test_enforce_requires_tls_and_pkix(): void
    {
        $target = new EndpointTarget('mx.test', 0, '8.8.8.8', 4);
        $probe = new ProbeResult([], true, [], new TlsObservation(true, 'TLSv1.3', 'x', 256));
        $finding = app(MtaStsCheck::class)->evaluate($target, $probe, ['state' => 'valid', 'mode' => 'enforce', 'mx' => ['mx.test'], 'pkix_valid' => false]);
        $this->assertSame(Outcome::Fail, $finding->outcome);
    }
}
