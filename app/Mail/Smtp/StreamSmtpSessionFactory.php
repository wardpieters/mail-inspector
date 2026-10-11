<?php

namespace App\Mail\Smtp;

use App\Mail\Network\TargetGuard;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ScanBudget;

class StreamSmtpSessionFactory implements SmtpSessionFactory
{
    public function __construct(private TargetGuard $guard) {}

    public function open(EndpointTarget $target, ScanBudget $budget): SmtpSession
    {
        $this->guard->assertPublic($target->ip);
        $address = $target->family === 6 ? '['.$target->ip.']' : $target->ip;
        $context = stream_context_create(['ssl' => ['peer_name' => $target->mx, 'SNI_enabled' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'capture_peer_cert' => true, 'capture_peer_cert_chain' => true, 'disable_compression' => true]]);
        $socket = @stream_socket_client('tcp://'.$address.':25', $errno, $error, $budget->timeout((float) config('mail_scan.connect_seconds', 5)), STREAM_CLIENT_CONNECT, $context);
        if (! $socket) {
            throw new \RuntimeException('TCP connection failed: '.substr($error, 0, 200), $errno);
        }

        return new StreamSmtpSession($socket, $budget, app(TranscriptRedactor::class));
    }
}
