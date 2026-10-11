<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('scans:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('scans:reconcile')->everyMinute()->withoutOverlapping();
Schedule::command('scans:prune')->daily()->withoutOverlapping();
