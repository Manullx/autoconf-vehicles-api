<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\VehicleController;

Route::get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('vehicle')->group( function () {

    Route::post('/', [VehicleController::class, 'store']);
    Route::get('/', [VehicleController::class, 'findAll']);
    Route::get('/{id}', [VehicleController::class, 'findOne']);
    Route::delete('/{id}', [VehicleController::class, 'remove']);

});