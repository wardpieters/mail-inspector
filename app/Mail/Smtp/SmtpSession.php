<?php

namespace App\Mail\Smtp;

use App\Mail\Value\SmtpReply;

interface SmtpSession
{
    public function greeting(): SmtpReply;

    public function command(string $line): SmtpReply;

    public function transcript(): array;

    public function close(): void;
}
