<?php

namespace App\Mail\Smtp;

use App\Mail\Value\ScanBudget;
use App\Mail\Value\SmtpReply;

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
