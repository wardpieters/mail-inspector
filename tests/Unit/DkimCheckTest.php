<?php

namespace Tests\Unit;

use App\Mail\Checks\DkimCheck;
use App\Mail\Dns\DnsClient;
use App\Mail\Enums\Outcome;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\ScanInput;
use Tests\Support\FakeDnsClient;
use Tests\TestCase;

class DkimCheckTest extends TestCase
{
    public function test_no_selector_is_skipped(): void
    {
        $this->app->instance(DnsClient::class, new FakeDnsClient);
        $this->assertSame(Outcome::Skipped, app(DkimCheck::class)->analyze(new ScanInput('fixture.test'), new ScanBudget(300))[0]->outcome);
    }

    public function test_selector_keys_and_revocation_are_distinct(): void
    {
        $dns = new FakeDnsClient;
        $dns->add('good._domainkey.fixture.test', 'TXT', [['text' => 'v=DKIM1; k=ed25519; p='.base64_encode(str_repeat('a', 32))]]);
        $dns->add('revoked._domainkey.fixture.test', 'TXT', [['text' => 'v=DKIM1; p=']]);
        $this->app->instance(DnsClient::class, $dns);
        $findings = app(DkimCheck::class)->analyze(new ScanInput('fixture.test', ['good', 'revoked']), new ScanBudget(300));
        $this->assertSame(Outcome::Pass, $findings[0]->outcome);
        $this->assertSame(Outcome::Fail, $findings[1]->outcome);
        $this->assertSame('good', $findings[0]->scope);
    }

    public function test_rejects_duplicate_tags_and_invalid_rsa_key(): void
    {
        $dns = new FakeDnsClient;
        $dns->add('selector._domainkey.fixture.test', 'TXT', [['text' => 'v=DKIM1; p=abc; p=def']]);
        $this->app->instance(DnsClient::class, $dns);
        $this->assertSame(Outcome::Fail, app(DkimCheck::class)->analyze(new ScanInput('fixture.test', ['selector']), new ScanBudget(300))[0]->outcome);
    }
}
