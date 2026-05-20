<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SolicitudServicio;
use Tymon\JWTAuth\Facades\JWTAuth;

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
        $solicitud->update([
            'estado' => 'aceptada'
        ]);

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
}
