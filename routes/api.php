<?php

use App\Http\Controllers\Api\V1\FreelancerIntakeController;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\VerifyIntegrationSignature;
use Illuminate\Support\Facades\Route;

/*
 * Agency API, served on the agency's own host (e.g. tsf.example.com/api/v1/...).
 */
Route::prefix('v1')->middleware([ResolveTenant::class])->group(function () {
    Route::post('intake/freelancers', FreelancerIntakeController::class)
        ->middleware(['throttle:intake', VerifyIntegrationSignature::class.':intake:freelancers'])
        ->name('api.v1.intake.freelancers');
});
