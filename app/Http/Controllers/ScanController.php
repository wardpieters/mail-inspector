<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScanRequest;
use App\Http\Resources\ScanReportResource;
use App\Mail\Scans\SubmitScan;
use App\Mail\Value\ScanInput;
use App\Models\Scan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScanController extends Controller
{
    public function store(StoreScanRequest $request, SubmitScan $submit): RedirectResponse
    {
        $input = ScanInput::fromRaw($request->validated('domain'), $request->validated('selectors', []));
        $scan = $submit->submit($input, $request->boolean('hidden_from_overview'));

        return redirect()->route('scans.show', $scan->public_id)->with('message', $scan->wasRecentlyCreated
            ? 'Scan queued.'
            : 'Showing the existing report. Its original DKIM selectors are unchanged.');
    }

    public function show(string $publicId): Response
    {
        $scan = Scan::where('public_id', $publicId)->where('created_at', '>', now()->subDays(config('mail_scan.retention_days', 7)))->firstOrFail();

        return Inertia::render('Scans/Show', [
            'report' => (new ScanReportResource($scan))->resolve(),
            'message' => session('message') ? __(session('message')) : null,
            'cooldown' => [
                'available_at' => $scan->created_at->copy()->addSeconds(SubmitScan::COOLDOWN_SECONDS)->toIso8601String(),
                'server_now' => now()->toIso8601String(),
            ],
        ]);
    }
}
