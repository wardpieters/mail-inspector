<?php

namespace App\Mail\Smtp;

use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\TlsObservation;

class TlsNegotiator
{
    public function negotiate(SmtpSession $session, EndpointTarget $target, ScanBudget $budget): TlsObservation
    {
        $budget->check();
        $reply = $session->command('STARTTLS');
        if ($reply->code !== 220) {
            return new TlsObservation(false, error: 'STARTTLS was not accepted.');
        }

        return $session->startTls($target->mx);
    }
}
