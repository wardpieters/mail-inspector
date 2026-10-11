<?php

use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScanExportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home', ['submit_url' => route('scans.store')]))->name('home');
Route::post('/scans', [ScanController::class, 'store'])->middleware('throttle:scans')->name('scans.store');
Route::get('/scans/{publicId}', [ScanController::class, 'show'])->whereUuid('publicId')->name('scans.show');

Route::get('/scans/{publicId}/export', ScanExportController::class)->whereUuid('publicId')->name('scans.export');
