<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/vehicles', [VehicleController::class, 'findAll']);

    Route::apiResource('users', UserController::class);

    Route::prefix('vehicle')->group(function () {

        Route::post('/', [VehicleController::class, 'store']);
        Route::get('/', [VehicleController::class, 'findAll']);
        Route::get('/{id}', [VehicleController::class, 'findOne']);
        Route::put('/{id}', [VehicleController::class, 'update']);
        Route::patch('/{id}', [VehicleController::class, 'patch']);
        Route::delete('/{id}', [VehicleController::class, 'remove']);

    });
});
