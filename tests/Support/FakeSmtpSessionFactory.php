<?php

namespace Tests\Support;

use App\Mail\Smtp\SmtpSession;
use App\Mail\Smtp\SmtpSessionFactory;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ScanBudget;

class FakeSmtpSessionFactory implements SmtpSessionFactory
{
    public array $targets = [];

    public function __construct(public FakeSmtpSession $session = new FakeSmtpSession(['greeting' => [220, ['mx.test ESMTP']], 'EHLO' => [250, ['mx.test', 'SIZE 100000']]])) {}

    public function open(EndpointTarget $target, ScanBudget $budget): SmtpSession
    {
        $this->targets[] = $target;

        return $this->session;
    }
}
