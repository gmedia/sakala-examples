<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReportController::class, 'index'])->name('reports.index');

Route::post('/reports', [ReportController::class, 'store'])
    ->middleware('throttle:reports')
    ->name('reports.store');
