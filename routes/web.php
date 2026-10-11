<?php
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
Route::get('/', fn() => Inertia::render('Home'))->name('home');
Route::post('/scans',[\App\Http\Controllers\ScanController::class,'store'])->middleware('throttle:scans')->name('scans.store');
Route::get('/scans/{publicId}',[\App\Http\Controllers\ScanController::class,'show'])->whereUuid('publicId')->name('scans.show');
