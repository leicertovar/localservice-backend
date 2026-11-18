<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Servicio;
use App\Models\ServicioImagen;
use App\Models\DenunciaImagen;
use App\Support\Imagenes;
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
        // El proveedor ve todas sus fotos, también las que el administrador ocultó
        $servicios = Servicio::where('usuario_id', $usuario->id)
            ->with('imagenes:id,servicio_id,ruta,orden,esta_ocultada')
            ->orderBy('created_at', 'desc')
            ->get();

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
            // Campos de la app móvil; el panel web aún no los envía
            'zona' => 'nullable|string|max:255',
            'tipo_cobro' => 'nullable|in:fijo,hora',
        ]);

        // Crear servicio
        $servicio = Servicio::create([
            'usuario_id' => $usuario->id,
            'nombre' => $datosValidados['nombre'],
            'categoria' => $datosValidados['categoria'],
            'precio' => $datosValidados['precio'],
            'descripcion' => $datosValidados['descripcion'] ?? null,
            'zona' => $datosValidados['zona'] ?? null,
            'tipo_cobro' => $datosValidados['tipo_cobro'] ?? 'fijo',
            'esta_activo' => true
        ]);

        return response()->json([
            'mensaje' => 'Servicio creado con éxito.',
            'servicio' => $servicio->load('imagenes')
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
            // Campos de la app móvil; el panel web aún no los envía
            'zona' => 'nullable|string|max:255',
            'tipo_cobro' => 'nullable|in:fijo,hora',
        ]);

        // Actualizar el servicio
        $servicio->update([
            'nombre' => $datosValidados['nombre'],
            'categoria' => $datosValidados['categoria'],
            'precio' => $datosValidados['precio'],
            'descripcion' => $datosValidados['descripcion'] ?? null,
        ]);

        // Solo si se envían: así una edición desde el panel web no borra la zona ni el cobro
        if ($request->has('zona')) {
            $servicio->zona = $datosValidados['zona'] ?? null;
        }
        if ($request->has('tipo_cobro')) {
            $servicio->tipo_cobro = $datosValidados['tipo_cobro'] ?? 'fijo';
        }
        $servicio->save();

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

        // Eliminar de forma definitiva (también sus fotos del disco)
        $rutas = $servicio->imagenes()->pluck('ruta');
        $servicio->delete();
        $rutas->each(fn ($r) => Imagenes::borrar($r));

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

    /**
     * Agrega fotos a un servicio propio (máximo 3 en total). multipart: imagenes[]
     */
    public function subirImagenes(Request $request, $id)
    {
        $usuario = JWTAuth::user();
        $servicio = $usuario ? Servicio::where('id', $id)->where('usuario_id', $usuario->id)->first() : null;
        if (!$servicio) {
            return response()->json(['mensaje' => 'Servicio no encontrado.'], 404);
        }

        $disponibles = Imagenes::MAXIMO - $servicio->imagenes()->count();
        if ($disponibles <= 0) {
            return response()->json(['mensaje' => 'Este servicio ya tiene ' . Imagenes::MAXIMO . ' fotos. Quita una para subir otra.'], 422);
        }
        $request->validate(['imagenes' => 'required|array|min:1', ...Imagenes::reglas($disponibles)], [
            'imagenes.required' => 'Elige al menos una foto.',
            ...Imagenes::mensajes($disponibles),
        ]);

        $orden = (int) $servicio->imagenes()->max('orden') + 1;
        foreach ($request->file('imagenes') as $archivo) {
            ServicioImagen::create([
                'servicio_id' => $servicio->id,
                'ruta' => Imagenes::guardar($archivo, 'servicios'),
                'orden' => $orden++,
            ]);
        }

        return response()->json([
            'mensaje' => 'Fotos agregadas.',
            'servicio' => $servicio->load('imagenes:id,servicio_id,ruta,orden,esta_ocultada'),
        ], 201);
    }

    /**
     * Quita una foto de un servicio propio.
     */
    public function eliminarImagen($id, $imagenId)
    {
        $usuario = JWTAuth::user();
        $imagen = ServicioImagen::where('id', $imagenId)
            ->where('servicio_id', $id)
            ->whereHas('servicio', fn ($q) => $q->where('usuario_id', $usuario?->id))
            ->first();
        if (!$imagen) {
            return response()->json(['mensaje' => 'Foto no encontrada.'], 404);
        }

        Imagenes::borrar($imagen->ruta);
        $imagen->delete();
        // Sus denuncias pendientes ya no aplican
        DenunciaImagen::where('tipo', 'servicio')->where('imagen_id', $imagen->id)->delete();

        return response()->json(['mensaje' => 'Foto eliminada.']);
    }
}
