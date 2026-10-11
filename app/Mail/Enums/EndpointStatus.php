<?php

namespace App\Mail\Enums;

enum EndpointStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
}
