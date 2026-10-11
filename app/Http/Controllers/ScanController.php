<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScanRequest;
use App\Http\Resources\ScanReportResource;
use App\Mail\Scans\SubmitScan;
use App\Mail\Value\ScanInput;
use App\Models\Scan;
use Inertia\Inertia;

class ScanController extends Controller
{
    public function store(StoreScanRequest $request, SubmitScan $submit)
    {
        $input = ScanInput::fromRaw($request->validated('domain'), $request->validated('selectors', []));
        $scan = $submit->submit($input);

        return redirect()->route('scans.show', $scan->public_id)->with('message', 'Scan queued. An existing active scan keeps its original selectors.');
    }

    public function show(string $publicId)
    {
        $scan = Scan::where('public_id', $publicId)->where('created_at', '>', now()->subDays(config('mail_scan.retention_days', 7)))->firstOrFail();

        return Inertia::render('Scans/Show', ['report' => (new ScanReportResource($scan))->resolve(), 'message' => session('message') ? __(session('message')) : null]);
    }
}
