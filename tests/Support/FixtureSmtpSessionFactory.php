<?php

namespace Tests\Support;

use App\Mail\Smtp\SmtpSession;
use App\Mail\Smtp\SmtpSessionFactory;
use App\Mail\Smtp\StreamSmtpSession;
use App\Mail\Smtp\TranscriptRedactor;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ScanBudget;

class FixtureSmtpSessionFactory implements SmtpSessionFactory
{
    public function __construct(private array $fixtures) {}

    public function open(EndpointTarget $target, ScanBudget $budget): SmtpSession
    {
        if (! app()->environment('testing')) {
            throw new \LogicException('Fixture factory is only for tests.');
        }
        $fixture = $this->fixtures[$target->family];
        $address = $fixture->address;
        if ($target->family === 6 && ! str_starts_with($address, '[')) {
            $address = '['.substr($address, 0, strrpos($address, ':')).']'.substr($address, strrpos($address, ':'));
        }
        $context = stream_context_create(['ssl' => ['peer_name' => $target->mx, 'SNI_enabled' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'capture_peer_cert' => true, 'capture_peer_cert_chain' => true]]);
        $socket = stream_socket_client('tcp://'.$address, $errno, $error, $budget->timeout(5), STREAM_CLIENT_CONNECT, $context);
        if (! $socket) {
            throw new \RuntimeException($error, $errno);
        }

        return new StreamSmtpSession($socket, $budget, new TranscriptRedactor);
    }
}
