<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/prueba', function () {
    return response()->json([
        'mensaje' => 'API funcionando correctamente'
    ]);
});


Route::group([

    'prefix' => 'auth'

    ], function () {

    Route::post('/registrer', [AuthController::class, 'registrer'])->name('auth.registrer');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');

    // Nuevas rutas protegidas
    Route::middleware('auth:api')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',     [AuthController::class, 'me']);
    });
    
});