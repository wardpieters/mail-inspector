<?php

use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScanExportController;
use App\Models\Scan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home', [
    'submit_url' => route('scans.store'),
    'retention_days' => (int) config('mail_scan.retention_days', 7),
    'recent_scans' => fn () => Scan::query()
        ->where('hidden_from_overview', false)
        ->where('created_at', '>', now()->subDays(config('mail_scan.retention_days', 7)))
        ->latest('created_at')->latest('id')->limit(5)
        ->get(['public_id', 'domain', 'status', 'created_at'])
        ->map(fn (Scan $scan): array => [
            'domain' => $scan->domain,
            'status' => $scan->status->value,
            'created_at' => $scan->created_at->toIso8601String(),
            'url' => route('scans.show', $scan->public_id),
        ]),
]))->name('home');
Route::get('/privacy', fn () => Inertia::render('Privacy', [
    'retention_days' => (int) config('mail_scan.retention_days', 7),
    'contact' => [
        'name' => config('privacy.contact_name'),
        'email' => config('privacy.contact_email'),
    ],
]))->name('privacy');
Route::post('/scans', [ScanController::class, 'store'])->middleware('throttle:scans')->name('scans.store');
Route::get('/scans/{publicId}', [ScanController::class, 'show'])->whereUuid('publicId')->name('scans.show');

Route::get('/scans/{publicId}/export', ScanExportController::class)->whereUuid('publicId')->name('scans.export');
