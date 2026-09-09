<?php

use App\Http\Controllers\Api\DriverLookupController;
use App\Http\Controllers\Api\VehicleLookupController;
use App\Http\Middleware\AuthenticateClaimsApi;
use Illuminate\Support\Facades\Route;

Route::middleware([AuthenticateClaimsApi::class])->prefix('v1')->group(function () {
    Route::get('vehicles', [VehicleLookupController::class, 'index']);
    Route::get('vehicles/{id}', [VehicleLookupController::class, 'show'])->whereNumber('id');
    Route::get('drivers', [DriverLookupController::class, 'index']);
    Route::get('drivers/{id}', [DriverLookupController::class, 'show'])->whereNumber('id');
});
