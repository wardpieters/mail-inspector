<?php

namespace App\Mail\Smtp;

use App\Mail\Value\ScanBudget;
use App\Mail\Value\SmtpReply;
use App\Mail\Value\TlsObservation;

class StreamSmtpSession implements SmtpSession
{
    private array $log = [];

    private int $bytes = 0;

    private bool $closed = false;

    /** @param resource $socket */
    public function __construct(private mixed $socket, private ScanBudget $budget, private TranscriptRedactor $redactor) {}

    public function greeting(): SmtpReply
    {
        return $this->read();
    }

    public function command(string $line): SmtpReply
    {
        if (strlen($line) > 510 || str_contains($line, "\r") || str_contains($line, "\n") || ! preg_match('/^(?:EHLO [a-z0-9.-]+|HELO [a-z0-9.-]+|STARTTLS|MAIL FROM:<[^<>\s]*>|RCPT TO:<[^<>\s]+>|RSET|QUIT)$/iD', $line)) {
            throw new \InvalidArgumentException('Disallowed SMTP command.');
        }
        $this->timeout();
        $framed = $line."\r\n";
        $written = 0;
        while ($written < strlen($framed)) {
            $this->timeout();
            $count = @fwrite($this->socket, substr($framed, $written));
            if (! $count) {
                throw new \RuntimeException('SMTP command write failed.');
            }$written += $count;
        }
        $this->record('client', $line);

        return $this->read();
    }

    private function timeout(): void
    {
        if ($this->closed) {
            throw new \RuntimeException('SMTP session is closed.');
        }
        $seconds = $this->budget->timeout((float) config('mail_scan.command_seconds', 10));
        stream_set_timeout($this->socket, (int) $seconds, (int) (($seconds - floor($seconds)) * 1000000));
    }

    private function read(): SmtpReply
    {
        $reply = (new ReplyReader)->read(function (): string {
            $this->timeout();
            $part = @fgets($this->socket, 1002);
            $metadata = stream_get_meta_data($this->socket);
            if ($metadata['timed_out']) {
                throw new \RuntimeException('SMTP reply timed out.');
            }

            return $part === false ? '' : $part;
        });
        foreach ($reply->lines as $line) {
            $this->record('server', $reply->code.' '.$line);
        }

        return $reply;
    }

    private function record(string $direction, string $line): void
    {
        $line = $this->redactor->redact($line);
        $remaining = (int) config('mail_scan.max_transcript', 32768) - $this->bytes;
        if ($remaining > 0) {
            $line = mb_strcut($line, 0, $remaining, 'UTF-8');
            $this->log[] = ['direction' => $direction, 'line' => $line];
            $this->bytes += strlen($line);
        }
    }

    public function startTls(string $mx, ?int $protocolMask = null): TlsObservation
    {
        $this->timeout();
        stream_context_set_option($this->socket, 'ssl', 'peer_name', $mx);
        $error = null;
        set_error_handler(function (int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });
        try {
            $success = stream_socket_enable_crypto($this->socket, true, $protocolMask ?? (STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT));
        } finally {
            restore_error_handler();
        }
        if ($success !== true) {
            return new TlsObservation(false, error: substr($error ?? 'TLS handshake failed.', 0, 500), clientCanOffer: ! preg_match('/no ciphers|no protocols|unsupported protocol|no suitable signature/i', $error ?? ''));
        }
        $crypto = stream_get_meta_data($this->socket)['crypto'] ?? [];
        $options = stream_context_get_options($this->socket);
        $chain = [];
        foreach (array_slice($options['ssl']['peer_certificate_chain'] ?? [$options['ssl']['peer_certificate'] ?? null], 0, 16) as $cert) {
            if ($cert && openssl_x509_export($cert, $pem)) {
                $chain[] = $pem;
            }
        }

        return new TlsObservation(true, $crypto['protocol'] ?? '', $crypto['cipher_name'] ?? '', (int) ($crypto['cipher_bits'] ?? 0), $chain);
    }

    public function transcript(): array
    {
        return $this->log;
    }

    public function close(): void
    {
        if (! $this->closed) {
            $this->closed = true;
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
