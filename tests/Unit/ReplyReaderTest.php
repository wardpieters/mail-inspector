<?php

namespace Tests\Unit;

use App\Mail\Smtp\ReplyReader;
use Tests\TestCase;

class ReplyReaderTest extends TestCase
{
    public function test_reads_split_multiline_reply(): void
    {
        $chunks = ['250-mx.test\r\n250-STA', 'RTTLS\r\n250 SIZE 1', '000\r\n'];
        $chunks = array_map(fn ($s) => str_replace(['\\r', '\\n'], ["\r", "\n"], $s), $chunks);
        $reply = (new ReplyReader)->read(function () use (&$chunks) {
            return array_shift($chunks);
        });
        $this->assertSame(250, $reply->code);
        $this->assertContains('STARTTLS', $reply->capabilities());
    }

    public function test_mixed_reply_codes_are_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ReplyReader)->read(fn () => "250-mx\r\n550 wrong\r\n");
    }
}
