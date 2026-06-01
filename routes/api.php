<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ResenaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\MensajeController;
use App\Http\Controllers\ClienteController;

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
        Route::get('/admin/proveedor/{id}/documento', [AuthController::class, 'verDocumentoProveedor']);
    });
    
});

// Rutas públicas para ver listado y detalles de proveedores
Route::get('/proveedores', [\App\Http\Controllers\ProveedorController::class, 'listarProveedores']);
Route::get('/proveedores/{id}', [\App\Http\Controllers\ProveedorController::class, 'obtenerPerfilProveedor']);
Route::get('/proveedores/{id}/resenas', [ResenaController::class, 'listarResenas']);

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
    Route::post('/solicitudes/{id}/completar', [\App\Http\Controllers\SolicitudServicioController::class, 'marcarCompletado']);
    Route::post('/solicitudes/{id}/confirmar', [\App\Http\Controllers\SolicitudServicioController::class, 'confirmarCompletado']);

    // Perfil del cliente
    Route::put('/cliente/perfil', [ClienteController::class, 'actualizarPerfil']);

    // Notificaciones
    Route::get('/notificaciones', [NotificacionController::class, 'listar']);
    Route::post('/notificaciones/leer-todas', [NotificacionController::class, 'marcarTodasLeidas']);
    Route::patch('/notificaciones/{id}/leer', [NotificacionController::class, 'marcarLeida']);

    // Chat / Mensajes
    Route::get('/mensajes/{usuarioId}', [MensajeController::class, 'obtenerConversacion']);
    Route::post('/mensajes', [MensajeController::class, 'enviarMensaje']);
    Route::get('/conversaciones', [MensajeController::class, 'listarConversaciones']);

    // Reseñas
    Route::post('/resenas', [ResenaController::class, 'crearResena']);
    Route::post('/resenas/{id}/responder', [ResenaController::class, 'responderResena']);
    Route::post('/resenas/{id}/reportar', [ResenaController::class, 'reportarResena']);
    Route::get('/admin/resenas-reportadas', [ResenaController::class, 'listarReportadas']);
    Route::patch('/admin/resenas/{id}/moderar', [ResenaController::class, 'moderarResena']);

    // Proveedor: foto de perfil (incluida en PUT /proveedor/perfil, pero ruta dedicada si se necesita form-data)
    Route::post('/proveedor/foto-perfil', [\App\Http\Controllers\ProveedorController::class, 'actualizarPerfilProveedor']);
});

