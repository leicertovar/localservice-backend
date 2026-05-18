<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Ruta básica para verificar el funcionamiento de la API
Route::get('/prueba', function () {
    return response()->json([
        'mensaje' => 'API de LocalService funcionando correctamente en español.'
    ]);
});

// Grupo de endpoints de autenticación y perfiles
Route::group([
    'prefix' => 'auth'
], function () {

    // Rutas de registro y acceso públicas
    Route::post('/registro', [AuthController::class, 'registrar'])->name('auth.registro');
    Route::post('/login', [AuthController::class, 'iniciarSesion'])->name('auth.login');
    Route::post('/refrescar', [AuthController::class, 'refrescarToken'])->name('auth.refrescar');

    // Rutas protegidas bajo el middleware de JWT (auth:api)
    Route::middleware('auth:api')->group(function () {

        // Rutas de sesión del usuario
        Route::post('/cerrar-sesion', [AuthController::class, 'cerrarSesion']);
        Route::get('/mi-perfil',      [AuthController::class, 'obtenerUsuarioAutenticado']);

        // Rutas Administrativas de control de calidad y aprobación
        Route::get('/admin/proveedores-pendientes',  [AuthController::class, 'obtenerProveedoresPendientes']);
        Route::post('/admin/aprobar-proveedor/{id}', [AuthController::class, 'aprobarProveedor']);
    });
    
});