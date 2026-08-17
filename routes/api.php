<?php

use App\Http\Controllers\VehicleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/vehicles', [VehicleController::class, 'findAll']);

    Route::prefix('vehicle')->group(function () {

        Route::post('/', [VehicleController::class, 'store']);
        Route::get('/', [VehicleController::class, 'findAll']);
        Route::get('/{id}', [VehicleController::class, 'findOne']);
        Route::put('/{id}', [VehicleController::class, 'update']);
        Route::patch('/{id}', [VehicleController::class, 'patch']);
        Route::delete('/{id}', [VehicleController::class, 'remove']);

    });
});
