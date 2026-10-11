<?php

namespace App\Mail\Smtp;

class TranscriptRedactor
{
    public function redact(string $line): string
    {
        $line = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '?', $line);
        $line = preg_replace('/((?:MAIL FROM|RCPT TO):)<[^>]*>/i', '$1<[redacted]>', $line);
        foreach (['sender', 'relay_sender', 'relay_recipient'] as $key) {
            $identity = config('mail_scan.'.$key);
            if ($identity) {
                $line = str_ireplace($identity, '[redacted]', $line);
            }
        }

        return mb_convert_encoding($line, 'UTF-8', 'UTF-8');
    }
}
