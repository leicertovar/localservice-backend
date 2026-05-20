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
    Route::post('/registro',             [AuthController::class, 'registrar'])->name('auth.registro');
    Route::post('/login',                [AuthController::class, 'iniciarSesion'])->name('auth.login');
    Route::post('/refrescar',            [AuthController::class, 'refrescarToken'])->name('auth.refrescar');
    Route::post('/recuperar-password',   [AuthController::class, 'solicitarRecuperacionPassword']);
    Route::post('/restablecer-password', [AuthController::class, 'restablecerPassword']);

    // Rutas protegidas bajo el middleware de JWT (auth:api)
    Route::middleware('auth:api')->group(function () {

        // Rutas de sesión del usuario
        Route::post('/cerrar-sesion', [AuthController::class, 'cerrarSesion']);
        Route::get('/mi-perfil',      [AuthController::class, 'obtenerUsuarioAutenticado']);

        // Rutas Administrativas de control de calidad y aprobación
        Route::get('/admin/proveedores-pendientes',  [AuthController::class, 'obtenerProveedoresPendientes']);
        Route::post('/admin/aprobar-proveedor/{id}', [AuthController::class, 'aprobarProveedor']);
        Route::get('/admin/usuarios',                [AuthController::class, 'obtenerUsuarios']);
        Route::delete('/admin/usuarios/{id}',        [AuthController::class, 'eliminarUsuario']);
        // Rechazar verificación y eliminar solicitud de proveedor
        Route::post('/admin/rechazar-proveedor/{id}', [AuthController::class, 'rechazarProveedor']);
        Route::get('/admin/estadisticas',            [AuthController::class, 'obtenerEstadisticas']);
    });
    
});

// Rutas públicas para ver listado y detalles de proveedores
Route::get('/proveedores', [\App\Http\Controllers\ProveedorController::class, 'listarProveedores']);
Route::get('/proveedores/{id}', [\App\Http\Controllers\ProveedorController::class, 'obtenerPerfilProveedor']);

// Rutas protegidas para la gestión de perfil de proveedor, servicios y agenda/reservas
Route::middleware('auth:api')->group(function () {
    // Actualizar perfil
    Route::put('/proveedor/perfil', [\App\Http\Controllers\ProveedorController::class, 'actualizarPerfilProveedor']);

    // CRUD de servicios
    Route::get('/servicios', [\App\Http\Controllers\ServicioController::class, 'listarServicios']);
    Route::post('/servicios', [\App\Http\Controllers\ServicioController::class, 'crearServicio']);
    Route::put('/servicios/{id}', [\App\Http\Controllers\ServicioController::class, 'actualizarServicio']);
    Route::delete('/servicios/{id}', [\App\Http\Controllers\ServicioController::class, 'eliminarServicio']);
    Route::patch('/servicios/{id}/activar', [\App\Http\Controllers\ServicioController::class, 'alternarEstadoServicio']);

    // Solicitudes, Cotizaciones y Reservas
    Route::post('/solicitudes', [\App\Http\Controllers\SolicitudServicioController::class, 'crearSolicitud']);
    Route::get('/proveedor/solicitudes', [\App\Http\Controllers\SolicitudServicioController::class, 'obtenerSolicitudesProveedor']);
    Route::get('/cliente/solicitudes', [\App\Http\Controllers\SolicitudServicioController::class, 'obtenerSolicitudesCliente']);
    Route::post('/solicitudes/{id}/cotizar', [\App\Http\Controllers\SolicitudServicioController::class, 'cotizarSolicitud']);
    Route::post('/solicitudes/{id}/aceptar', [\App\Http\Controllers\SolicitudServicioController::class, 'aceptarCotizacion']);
    Route::post('/solicitudes/{id}/rechazar', [\App\Http\Controllers\SolicitudServicioController::class, 'rechazarSolicitud']);
});

