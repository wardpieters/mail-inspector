<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
try {
    $app->make(Kernel::class)->bootstrap();
    DB::select('SELECT 1');
    Redis::connection()->ping();
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Dependency health check failed.\n");
    exit(1);
}
