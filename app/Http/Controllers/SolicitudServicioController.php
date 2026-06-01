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
            'accion' => 'required|in:aceptar,rechazar',
            'queja' => 'nullable|string|max:1000',
        ]);

        $solicitud->update([
            'confirmacion_cliente' => $datos['accion'] === 'aceptar' ? 'aceptado' : 'rechazado',
            'queja_cliente' => $datos['queja'] ?? null,
        ]);

        if ($datos['accion'] === 'rechazar') {
            // Notificar al admin
            $admins = \App\Models\Usuario::where('rol_id', 3)->get();
            foreach ($admins as $admin) {
                Notificacion::crear(
                    $admin->id,
                    'sistema',
                    'Queja de cliente',
                    "El cliente {$cliente->nombre} rechazó el servicio completado. Queja: " . ($datos['queja'] ?? 'Sin descripción')
                );
            }
        }

        // Notificar al proveedor
        Notificacion::crear(
            $solicitud->proveedor_id,
            'pago',
            $datos['accion'] === 'aceptar' ? 'Servicio confirmado' : 'Servicio disputado',
            $datos['accion'] === 'aceptar'
                ? "{$cliente->nombre} confirmó el servicio completado."
                : "{$cliente->nombre} rechazó el servicio. Queja: " . ($datos['queja'] ?? 'Sin descripción')
        );

        return response()->json(['mensaje' => 'Confirmación registrada.', 'solicitud' => $solicitud]);
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
