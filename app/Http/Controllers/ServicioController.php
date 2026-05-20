<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Servicio;
use Tymon\JWTAuth\Facades\JWTAuth;

class ServicioController extends Controller
{
    /**
     * Lista todos los servicios del proveedor actualmente autenticado.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function listarServicios()
    {
        // Obtener usuario autenticado
        $usuario = JWTAuth::user();

        if (!$usuario || $usuario->rol_id !== 2) {
            return response()->json([
                'mensaje' => 'No autorizado o no es un proveedor.'
            ], 401);
        }

        // Obtener servicios del proveedor
        $servicios = Servicio::where('usuario_id', $usuario->id)->orderBy('created_at', 'desc')->get();

        return response()->json($servicios);
    }

    /**
     * Crea un nuevo servicio asociado al proveedor autenticado.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function crearServicio(Request $request)
    {
        // Obtener usuario autenticado
        $usuario = JWTAuth::user();

        if (!$usuario || $usuario->rol_id !== 2) {
            return response()->json([
                'mensaje' => 'No autorizado.'
            ], 401);
        }

        // Validar datos de entrada
        $datosValidados = $request->validate([
            'nombre' => 'required|string|max:255',
            'categoria' => 'required|string|max:255',
            'precio' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        // Crear servicio
        $servicio = Servicio::create([
            'usuario_id' => $usuario->id,
            'nombre' => $datosValidados['nombre'],
            'categoria' => $datosValidados['categoria'],
            'precio' => $datosValidados['precio'],
            'descripcion' => $datosValidados['descripcion'] ?? null,
            'esta_activo' => true
        ]);

        return response()->json([
            'mensaje' => 'Servicio creado con éxito.',
            'servicio' => $servicio
        ], 201);
    }

    /**
     * Actualiza un servicio existente del proveedor autenticado.
     * 
     * @param \Illuminate\Http\Request $request
     * @param int $id Identificador del servicio a modificar.
     * @return \Illuminate\Http\JsonResponse
     */
    public function actualizarServicio(Request $request, $id)
    {
        // Obtener usuario autenticado
        $usuario = JWTAuth::user();

        if (!$usuario) {
            return response()->json([
                'mensaje' => 'No autorizado.'
            ], 401);
        }

        // Buscar el servicio y verificar propiedad
        $servicio = Servicio::where('id', $id)->where('usuario_id', $usuario->id)->first();

        if (!$servicio) {
            return response()->json([
                'mensaje' => 'Servicio no encontrado o no pertenece a su perfil.'
            ], 404);
        }

        // Validar datos de entrada
        $datosValidados = $request->validate([
            'nombre' => 'required|string|max:255',
            'categoria' => 'required|string|max:255',
            'precio' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        // Actualizar el servicio
        $servicio->update([
            'nombre' => $datosValidados['nombre'],
            'categoria' => $datosValidados['categoria'],
            'precio' => $datosValidados['precio'],
            'descripcion' => $datosValidados['descripcion'] ?? null,
        ]);

        return response()->json([
            'mensaje' => 'Servicio actualizado con éxito.',
            'servicio' => $servicio
        ]);
    }

    /**
     * Elimina un servicio de la base de datos.
     * 
     * @param int $id Identificador del servicio a eliminar.
     * @return \Illuminate\Http\JsonResponse
     */
    public function eliminarServicio($id)
    {
        // Obtener usuario autenticado
        $usuario = JWTAuth::user();

        if (!$usuario) {
            return response()->json([
                'mensaje' => 'No autorizado.'
            ], 401);
        }

        // Buscar el servicio y verificar propiedad
        $servicio = Servicio::where('id', $id)->where('usuario_id', $usuario->id)->first();

        if (!$servicio) {
            return response()->json([
                'mensaje' => 'Servicio no encontrado.'
            ], 404);
        }

        // Eliminar de forma definitiva
        $servicio->delete();

        return response()->json([
            'mensaje' => 'Servicio eliminado correctamente.'
        ]);
    }

    /**
     * Alterna el estado activo/inactivo de un servicio.
     * 
     * @param int $id Identificador del servicio.
     * @return \Illuminate\Http\JsonResponse
     */
    public function alternarEstadoServicio($id)
    {
        // Obtener usuario autenticado
        $usuario = JWTAuth::user();

        if (!$usuario) {
            return response()->json([
                'mensaje' => 'No autorizado.'
            ], 401);
        }

        // Buscar el servicio
        $servicio = Servicio::where('id', $id)->where('usuario_id', $usuario->id)->first();

        if (!$servicio) {
            return response()->json([
                'mensaje' => 'Servicio no encontrado.'
            ], 404);
        }

        // Invertir el estado actual
        $servicio->esta_activo = !$servicio->esta_activo;
        $servicio->save();

        return response()->json([
            'mensaje' => $servicio->esta_activo ? 'Servicio activado.' : 'Servicio desactivado.',
            'servicio' => $servicio
        ]);
    }
}
