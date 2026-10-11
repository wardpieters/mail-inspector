<?php

namespace App\Mail\Smtp;

use App\Mail\Value\EndpointTarget;
use App\Mail\Value\ScanBudget;

interface SmtpSessionFactory
{
    public function open(EndpointTarget $target, ScanBudget $budget): SmtpSession;
}
