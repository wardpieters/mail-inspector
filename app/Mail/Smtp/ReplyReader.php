<?php

namespace App\Mail\Smtp;

use App\Mail\Value\SmtpReply;

class ReplyReader
{
    public function read(callable $chunk): SmtpReply
    {
        $buffer = '';
        $lines = [];
        $code = null;
        $bytes = 0;
        while (true) {
            $part = $chunk();
            if (! is_string($part) || $part === '') {
                throw new \RuntimeException('SMTP reply ended before completion.');
            }
            $bytes += strlen($part);
            if ($bytes > 16384) {
                throw new \RuntimeException('SMTP reply size limit exceeded.');
            }$buffer .= $part;
            while (($position = strpos($buffer, "\r\n")) !== false) {
                $line = substr($buffer, 0, $position);
                $buffer = substr($buffer, $position + 2);
                if (strlen($line) > 1000 || ! preg_match('/^([2-5][0-9]{2})([- ])(.*)$/D', $line, $match)) {
                    throw new \RuntimeException('Malformed SMTP reply.');
                }
                if ($code !== null && $code !== (int) $match[1]) {
                    throw new \RuntimeException('Mixed SMTP reply codes.');
                }
                $code = (int) $match[1];
                $lines[] = $match[3];
                if (count($lines) > 100) {
                    throw new \RuntimeException('SMTP multiline limit exceeded.');
                }
                if ($match[2] === ' ') {
                    if ($buffer !== '') {
                        throw new \RuntimeException('Unexpected data after SMTP reply.');
                    }

return new SmtpReply($code, $lines);
                }
            }
            if (strlen($buffer) > 1000) {
                throw new \RuntimeException('SMTP line length limit exceeded.');
            }
        }
    }
}
