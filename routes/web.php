<?php

use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\ReportExportController;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $adminHost = config('platform.admin_subdomain').'.'.config('platform.base_domain');

    return redirect($request->getHost() === $adminHost ? '/platform' : '/portal');
});

/*
 * Agency routes outside the panels. ResolveTenant is first in the "agency" group (see bootstrap/app.php),
 * so the session's user is always loaded inside the right agency.
 */
Route::middleware(['agency', SetLocale::class, 'throttle:60,1'])->group(function () {
    Route::get('documents/{document}/download', DocumentDownloadController::class)
        ->whereUuid('document')->name('documents.download');
    Route::get('crm/reports/export/{report}', ReportExportController::class)
        ->where('report', '[a-z-]{1,40}')->name('reports.export');
});
