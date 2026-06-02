<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SolicitudServicio;
use App\Models\Notificacion;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Carbon;

class SolicitudServicioController extends Controller
{
    /**
     * El cliente inicia una nueva solicitud de servicio/cotización.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function crearSolicitud(Request $request)
    {
        // Obtener usuario autenticado (debe ser cliente)
        $cliente = JWTAuth::user();

        if (!$cliente) {
            return response()->json([
                'mensaje' => 'No autorizado.'
            ], 401);
        }

        // Validar datos de entrada
        $datosValidados = $request->validate([
            'proveedor_id' => 'required|exists:usuarios,id',
            'servicio_id' => 'nullable|exists:servicios,id',
            'fecha' => 'required|date',
            'hora' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'telefono' => 'required|string|max:255',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
        ]);

        // Crear la solicitud
        $solicitud = SolicitudServicio::create([
            'cliente_id' => $cliente->id,
            'proveedor_id' => $datosValidados['proveedor_id'],
            'servicio_id' => $datosValidados['servicio_id'] ?? null,
            'fecha' => $datosValidados['fecha'],
            'hora' => $datosValidados['hora'],
            'direccion' => $datosValidados['direccion'],
            'descripcion' => $datosValidados['descripcion'],
            'telefono' => $datosValidados['telefono'],
            'latitud' => $datosValidados['latitud'] ?? null,
            'longitud' => $datosValidados['longitud'] ?? null,
            'estado' => 'pendiente'
        ]);

        return response()->json([
            'mensaje' => 'Solicitud de servicio enviada con éxito.',
            'solicitud' => $solicitud
        ], 201);
    }

    /**
     * El proveedor obtiene todas sus solicitudes de servicio recibidas.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerSolicitudesProveedor()
    {
        // Obtener proveedor autenticado
        $proveedor = JWTAuth::user();

        if (!$proveedor || $proveedor->rol_id !== 2) {
            return response()->json([
                'mensaje' => 'No autorizado o no es un proveedor.'
            ], 401);
        }

        // Obtener solicitudes
        $solicitudes = SolicitudServicio::where('proveedor_id', $proveedor->id)
            ->with(['cliente', 'servicio'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($solicitudes);
    }

    /**
     * El cliente obtiene todas las solicitudes de servicio que ha realizado.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerSolicitudesCliente()
    {
        // Obtener cliente autenticado
        $cliente = JWTAuth::user();

        if (!$cliente) {
            return response()->json([
                'mensaje' => 'No autorizado.'
            ], 401);
        }

        // Obtener solicitudes
        $solicitudes = SolicitudServicio::where('cliente_id', $cliente->id)
            ->with(['proveedor', 'servicio'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($solicitudes);
    }

    /**
     * El proveedor cotiza una solicitud de servicio pendiente.
     * 
     * @param \Illuminate\Http\Request $request
     * @param int $id Identificador de la solicitud.
     * @return \Illuminate\Http\JsonResponse
     */
    public function cotizarSolicitud(Request $request, $id)
    {
        // Obtener proveedor autenticado
        $proveedor = JWTAuth::user();

        if (!$proveedor) {
            return response()->json([
                'mensaje' => 'No autorizado.'
            ], 401);
        }

        // Buscar la solicitud recibida
        $solicitud = SolicitudServicio::where('id', $id)
            ->where('proveedor_id', $proveedor->id)
            ->first();

        if (!$solicitud) {
            return response()->json([
                'mensaje' => 'Solicitud no encontrada.'
            ], 404);
        }

        // Validar datos de la cotización
        $datosValidados = $request->validate([
            'monto_cotizado' => 'required|string|max:255',
            'tiempo_estimado' => 'required|string|max:255',
            'garantia' => 'nullable|string|max:255',
            'detalles_cotizacion' => 'nullable|string',
        ]);

        // Actualizar datos y cambiar estado a 'cotizado'
        $solicitud->update([
            'monto_cotizado' => $datosValidados['monto_cotizado'],
            'tiempo_estimado' => $datosValidados['tiempo_estimado'],
            'garantia' => $datosValidados['garantia'] ?? null,
            'detalles_cotizacion' => $datosValidados['detalles_cotizacion'] ?? null,
            'estado' => 'cotizado'
        ]);

        // Notificar al cliente
        Notificacion::crear(
            $solicitud->cliente_id,
            'solicitud',
            'Cotización recibida',
            "Tu proveedor envió una cotización de {$solicitud->monto_cotizado}. ¡Revísala!"
        );

        return response()->json([
            'mensaje' => 'Cotización enviada con éxito.',
            'solicitud' => $solicitud
        ]);
    }

    /**
     * El cliente acepta la cotización propuesta por el proveedor.
     * 
     * @param int $id Identificador de la solicitud.
     * @return \Illuminate\Http\JsonResponse
     */
    public function aceptarCotizacion($id)
    {
        // Obtener cliente autenticado
        $cliente = JWTAuth::user();

        if (!$cliente) {
            return response()->json([
                'mensaje' => 'No autorizado.'
            ], 401);
        }

        // Buscar la solicitud realizada
        $solicitud = SolicitudServicio::where('id', $id)
            ->where('cliente_id', $cliente->id)
            ->first();

        if (!$solicitud) {
            return response()->json([
                'mensaje' => 'Solicitud no encontrada.'
            ], 404);
        }

        // Actualizar estado a 'aceptada' (confirmado en agenda)
        $solicitud->update(['estado' => 'aceptada']);

        // Notificar al proveedor
        Notificacion::crear(
            $solicitud->proveedor_id,
            'solicitud',
            'Cotización aceptada',
            "{$cliente->nombre} aceptó tu cotización para el {$solicitud->fecha}."
        );

        return response()->json([
            'mensaje' => 'Cotización aceptada con éxito. El servicio ha sido programado.',
            'solicitud' => $solicitud
        ]);
    }

    /**
     * Rechaza la solicitud (tanto por el cliente como por el proveedor).
     * 
     * @param int $id Identificador de la solicitud.
     * @return \Illuminate\Http\JsonResponse
     */
    public function rechazarSolicitud($id)
    {
        // Obtener usuario autenticado
        $usuario = JWTAuth::user();

        if (!$usuario) {
            return response()->json([
                'mensaje' => 'No autorizado.'
            ], 401);
        }

        // Buscar la solicitud (debe pertenecer al cliente o al proveedor)
        $solicitud = SolicitudServicio::where('id', $id)
            ->where(function($query) use ($usuario) {
                $query->where('cliente_id', $usuario->id)
                      ->orWhere('proveedor_id', $usuario->id);
            })
            ->first();

        if (!$solicitud) {
            return response()->json([
                'mensaje' => 'Solicitud no encontrada.'
            ], 404);
        }

        // Actualizar estado a 'rechazada'
        $solicitud->update([
            'estado' => 'rechazada'
        ]);

        return response()->json([
            'mensaje' => 'Solicitud o cotización rechazada.',
            'solicitud' => $solicitud
        ]);
    }

    /**
     * El proveedor marca un servicio como completado y pagado.
     */
    public function marcarCompletado(Request $request, $id)
    {
        $proveedor = JWTAuth::user();

        if (!$proveedor || $proveedor->rol_id !== 2) {
            return response()->json(['mensaje' => 'No autorizado.'], 401);
        }

        $solicitud = SolicitudServicio::where('id', $id)
            ->where('proveedor_id', $proveedor->id)
            ->where('estado', 'aceptada')
            ->first();

        if (!$solicitud) {
            return response()->json(['mensaje' => 'Solicitud no encontrada o no está aceptada.'], 404);
        }

        // No permitir marcar como completado antes de la fecha acordada
        if ($solicitud->fecha) {
            $fechaAcordada = Carbon::parse($solicitud->fecha)->toDateString();
            $hoy = Carbon::now()->toDateString();
            if ($hoy < $fechaAcordada) {
                return response()->json([
                    'mensaje' => 'No puedes marcar el servicio como completado antes de la fecha acordada (' . Carbon::parse($solicitud->fecha)->format('d/m/Y') . ').'
                ], 422);
            }
        }

        $solicitud->update([
            'estado' => 'completada',
            'fecha_completado' => Carbon::now(),
            'marcado_pagado' => true,
        ]);

        // Notificar al cliente
        Notificacion::crear(
            $solicitud->cliente_id,
            'sistema',
            'Servicio completado',
            "El proveedor marcó el servicio como completado. Por favor confirma si está de acuerdo."
        );

        return response()->json(['mensaje' => 'Servicio marcado como completado.', 'solicitud' => $solicitud]);
    }

    /**
     * El cliente confirma o rechaza la completitud del servicio.
     * Si rechaza, puede adjuntar evidencia (foto/archivo) y un motivo.
     */
    public function confirmarCompletado(Request $request, $id)
    {
        $cliente = JWTAuth::user();

        if (!$cliente || $cliente->rol_id !== 1) {
            return response()->json(['mensaje' => 'No autorizado.'], 401);
        }

        $solicitud = SolicitudServicio::where('id', $id)
            ->where('cliente_id', $cliente->id)
            ->where('estado', 'completada')
            ->first();

        if (!$solicitud) {
            return response()->json(['mensaje' => 'Solicitud no encontrada.'], 404);
        }

        $datos = $request->validate([
            'accion'    => 'required|in:aceptar,rechazar',
            'queja'     => 'nullable|string|max:1000',
            'evidencia' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $urlEvidencia = null;
        if ($request->hasFile('evidencia')) {
            $archivo = $request->file('evidencia');
            $nombre = time() . '_ev_cliente_' . preg_replace('/\s+/', '_', $archivo->getClientOriginalName());
            $ruta = $archivo->storeAs('disputas', $nombre, 'public');
            $urlEvidencia = '/storage/' . $ruta;
        }

        if ($datos['accion'] === 'aceptar') {
            $solicitud->update([
                'confirmacion_cliente' => 'aceptado',
            ]);

            Notificacion::crear(
                $solicitud->proveedor_id,
                'pago',
                'Servicio confirmado',
                "{$cliente->nombre} confirmó el servicio completado. ¡El pago queda registrado!"
            );
        } else {
            // Rechazar: abrir disputa
            $solicitud->update([
                'confirmacion_cliente' => 'rechazado',
                'queja_cliente'        => $datos['queja'] ?? null,
                'evidencia_cliente'    => $urlEvidencia,
                'estado_disputa'       => 'pendiente_proveedor',
            ]);

            // Notificar al proveedor para que aporte su evidencia
            Notificacion::crear(
                $solicitud->proveedor_id,
                'sistema',
                'Disputa abierta',
                "{$cliente->nombre} rechazó el servicio. Motivo: " . ($datos['queja'] ?? 'Sin descripción') . ". Tienes 48h para aportar tu evidencia."
            );

            // Notificar al admin
            $admins = \App\Models\Usuario::where('rol_id', 3)->get();
            foreach ($admins as $admin) {
                Notificacion::crear(
                    $admin->id,
                    'sistema',
                    'Disputa de servicio',
                    "El cliente {$cliente->nombre} reportó que el proveedor intenta confirmar un trabajo incompleto. Solicitud #{$solicitud->id}."
                );
            }
        }

        return response()->json(['mensaje' => 'Confirmación registrada.', 'solicitud' => $solicitud->fresh()]);
    }

    /**
     * El proveedor aporta evidencia en una disputa activa.
     */
    public function aportarEvidenciaProveedor(Request $request, $id)
    {
        $proveedor = JWTAuth::user();

        if (!$proveedor || $proveedor->rol_id !== 2) {
            return response()->json(['mensaje' => 'No autorizado.'], 401);
        }

        $solicitud = SolicitudServicio::where('id', $id)
            ->where('proveedor_id', $proveedor->id)
            ->where('estado_disputa', 'pendiente_proveedor')
            ->first();

        if (!$solicitud) {
            return response()->json(['mensaje' => 'Disputa no encontrada o ya respondida.'], 404);
        }

        $datos = $request->validate([
            'descripcion' => 'required|string|max:2000',
            'evidencia'   => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $archivo = $request->file('evidencia');
        $nombre = time() . '_ev_proveedor_' . preg_replace('/\s+/', '_', $archivo->getClientOriginalName());
        $ruta = $archivo->storeAs('disputas', $nombre, 'public');

        $solicitud->update([
            'evidencia_proveedor' => '/storage/' . $ruta,
            'queja_cliente'       => $solicitud->queja_cliente . "\n\n[Proveedor] " . $datos['descripcion'],
            'estado_disputa'      => 'pendiente_admin',
        ]);

        // Notificar al admin para que tome la decisión final
        $admins = \App\Models\Usuario::where('rol_id', 3)->get();
        foreach ($admins as $admin) {
            Notificacion::crear(
                $admin->id,
                'sistema',
                'Disputa lista para resolver',
                "El proveedor {$proveedor->nombre} aportó evidencia en la disputa #{$solicitud->id}. Requiere tu decisión final."
            );
        }

        return response()->json(['mensaje' => 'Evidencia enviada. El admin tomará la decisión final.', 'solicitud' => $solicitud->fresh()]);
    }

    /**
     * Admin resuelve una disputa, aprobando o rechazando el trabajo del proveedor.
     */
    public function resolverDisputa(Request $request, $id)
    {
        $admin = JWTAuth::user();

        if (!$admin || $admin->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }

        $solicitud = SolicitudServicio::where('id', $id)
            ->where('estado_disputa', 'pendiente_admin')
            ->first();

        if (!$solicitud) {
            return response()->json(['mensaje' => 'Disputa no encontrada o ya resuelta.'], 404);
        }

        $datos = $request->validate([
            'resolucion' => 'required|in:aprobado,rechazado',
            'nota'       => 'nullable|string|max:1000',
        ]);

        $solicitud->update([
            'resolucion_admin' => $datos['resolucion'],
            'nota_admin'       => $datos['nota'] ?? null,
            'estado_disputa'   => 'resuelto',
            'confirmacion_cliente' => $datos['resolucion'] === 'aprobado' ? 'aceptado' : 'rechazado',
        ]);

        // Notificar al proveedor
        Notificacion::crear(
            $solicitud->proveedor_id,
            'sistema',
            $datos['resolucion'] === 'aprobado' ? 'Disputa resuelta a tu favor' : 'Disputa resuelta en contra',
            $datos['resolucion'] === 'aprobado'
                ? "El administrador aprobó el trabajo. El pago queda confirmado. " . ($datos['nota'] ?? '')
                : "El administrador determinó que el trabajo no fue completado correctamente. " . ($datos['nota'] ?? '')
        );

        // Notificar al cliente
        Notificacion::crear(
            $solicitud->cliente_id,
            'sistema',
            'Resolución de disputa',
            $datos['resolucion'] === 'aprobado'
                ? "El administrador revisó la evidencia y aprobó el trabajo del proveedor. " . ($datos['nota'] ?? '')
                : "El administrador revisó la evidencia y determinó que el trabajo fue insatisfactorio. " . ($datos['nota'] ?? '')
        );

        return response()->json(['mensaje' => 'Disputa resuelta correctamente.', 'solicitud' => $solicitud->fresh()]);
    }

    /**
     * Admin: lista todas las disputas activas.
     */
    public function listarDisputas()
    {
        $admin = JWTAuth::user();
        if (!$admin || $admin->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }

        $disputas = SolicitudServicio::whereIn('estado_disputa', ['pendiente_proveedor', 'pendiente_admin'])
            ->with(['cliente:id,nombre,apellido', 'proveedor:id,nombre,apellido', 'servicio:id,nombre'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($disputas);
    }

    /**
     * El proveedor cotiza una solicitud y genera notificación al cliente.
     */
    private function notificarCotizacion(SolicitudServicio $solicitud): void
    {
        Notificacion::crear(
            $solicitud->cliente_id,
            'solicitud',
            'Cotización recibida',
            "Tu proveedor envió una cotización de {$solicitud->monto_cotizado}. ¡Revísala!"
        );
    }
}
