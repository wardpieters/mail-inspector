<?php

namespace App\Http\Controllers;

use App\Http\Resources\ScanReportResource;
use App\Models\Scan;
use Illuminate\Http\JsonResponse;

class ScanExportController extends Controller
{
    public function __invoke(string $publicId): JsonResponse
    {
        $scan = Scan::where('public_id', $publicId)->where('created_at', '>', now()->subDays(config('mail_scan.retention_days', 7)))->firstOrFail();

        return response()->json((new ScanReportResource($scan))->resolve(), 200, ['Content-Disposition' => 'attachment; filename="mail-report-'.$scan->domain.'.json"', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
