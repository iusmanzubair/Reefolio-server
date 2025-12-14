<?php

use App\Http\Controllers\ReportController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::post('/api/extract-report-gemini', [ReportController::class, 'extract']);
Route::get('/api/fetch-themes', [TemplateController::class, 'fetchThemes']);
Route::post('/api/create-portfolio', [TemplateController::class, 'createPortfolio']);

require __DIR__.'/auth.php';
