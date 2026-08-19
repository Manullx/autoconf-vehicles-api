<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleImageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('/password', [AuthController::class, 'storePassword'])->middleware('throttle:5,1');
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('users', UserController::class);

    Route::apiResource('vehicles', VehicleController::class);
    Route::post('/vehicles/{vehicleId}/images', [VehicleImageController::class, 'store'])->middleware('throttle:30,1');
    Route::patch('/vehicles/{vehicleId}/images/{imageId}/cover', [VehicleImageController::class, 'cover'])->middleware('throttle:30,1');
    Route::delete('/vehicles/{vehicleId}/images/{imageId}', [VehicleImageController::class, 'destroy'])->middleware('throttle:30,1');
});
