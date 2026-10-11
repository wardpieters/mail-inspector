<?php

namespace App\Mail\Smtp;

use App\Mail\Value\SmtpReply;
use App\Mail\Value\TlsObservation;

interface SmtpSession
{
    public function startTls(string $mx, ?int $protocolMask = null): TlsObservation;

    public function greeting(): SmtpReply;

    public function command(string $line): SmtpReply;

    public function transcript(): array;

    public function close(): void;
}
