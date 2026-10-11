<?php

namespace App\Mail\Smtp;

use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ScanBudget;

class LegacyTlsProbe
{
    public function __construct(private SmtpSessionFactory $factory) {}

    public function probe(EndpointTarget $target, ScanBudget $budget): array
    {
        $attempts = [];
        foreach (['TLSv1' => STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT, 'TLSv1.1' => STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT] as $version => $mask) {
            $session = null;
            if ($budget->remaining() < 2) {
                $attempts[] = ['version' => $version, 'status' => 'inconclusive', 'reason' => 'Endpoint deadline left insufficient time.'];

                continue;
            }
            try {
                $session = $this->factory->open($target, $budget);
                if ($session->greeting()->code !== 220) {
                    throw new \RuntimeException('No SMTP greeting.');
                }
                $ehlo = $session->command('EHLO '.config('mail_scan.helo'));
                if (! in_array('STARTTLS', $ehlo->capabilities(), true) || $session->command('STARTTLS')->code !== 220) {
                    throw new \RuntimeException('STARTTLS not available in the separate session.');
                }
                $tls = $session->startTls($target->mx, $mask);
                $attempts[] = ['version' => $version, 'status' => $tls->success ? 'accepted' : ($tls->clientCanOffer && preg_match('/alert.*protocol version/i', $tls->error ?? '') ? 'rejected' : 'inconclusive'), 'observation' => $tls->jsonSerialize()];
            } catch (\Throwable $e) {
                $attempts[] = ['version' => $version, 'status' => 'inconclusive', 'reason' => substr($e->getMessage(), 0, 300)];
            } finally {
                if ($session) {
                    $session->close();
                }
            }
        }

        return $attempts;
    }
}
