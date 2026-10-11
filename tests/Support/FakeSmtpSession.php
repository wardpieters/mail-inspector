<?php

namespace Tests\Support;

use App\Mail\Smtp\SmtpSession;
use App\Mail\Value\SmtpReply;
use App\Mail\Value\TlsObservation;

class FakeSmtpSession implements SmtpSession
{
    public array $commands = [];

    public bool $closed = false;

    public function __construct(public array $script = []) {}

    public function startTls(string $mx, ?int $protocolMask = null): TlsObservation
    {
        return new TlsObservation(true, 'TLSv1.3', 'TLS_AES_256_GCM_SHA384', 256);
    }

    public function greeting(): SmtpReply
    {
        return $this->reply('greeting');
    }

    public function command(string $line): SmtpReply
    {
        $this->commands[] = $line;

        return $this->reply(explode(' ', $line)[0]);
    }

    private function reply(string $verb): SmtpReply
    {
        $data = $this->script[$verb] ?? [250, ['OK']];

        return new SmtpReply($data[0], $data[1]);
    }

    public function transcript(): array
    {
        return $this->commands;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
