<?php

namespace App\Mail\Enums;

enum Outcome: string
{
    case Pass = 'pass';
    case Warning = 'warning';
    case Fail = 'fail';
    case Info = 'info';
    case Skipped = 'skipped';
    case Inconclusive = 'inconclusive';
    case NotApplicable = 'not_applicable';
}
