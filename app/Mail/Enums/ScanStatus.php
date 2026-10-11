<?php
namespace App\Mail\Enums;
enum ScanStatus: string {case Queued = 'queued'; case Running = 'running'; case Completed = 'completed'; case Partial = 'partial'; case Failed = 'failed';}
