<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Resena;
use App\Models\SolicitudServicio;
use App\Services\CalificacionProveedor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use App\Models\Notificacion;
use Tymon\JWTAuth\Facades\JWTAuth;

class ResenaController extends Controller
{
    /**
     * Lista las reseñas de un proveedor (público).
     */
    public function listarResenas($proveedorId)
    {
        $resenas = Resena::where('proveedor_id', $proveedorId)
            ->where('esta_ocultada', false)
            ->with('cliente:id,nombre,apellido')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($resenas);
    }

    /**
     * El cliente crea una reseña para un proveedor.
     */
    public function crearResena(Request $request)
    {
        $cliente = JWTAuth::user();

        if (!$cliente || $cliente->rol_id !== 1) {
            return response()->json(['mensaje' => 'Solo los clientes pueden dejar reseñas.'], 403);
        }

        $datos = $request->validate([
            'proveedor_id' => 'required|exists:usuarios,id',
            // Solo se califica un servicio concreto (HU: "solo disponible con solicitud completada")
            'solicitud_id' => 'required|integer',
            'calificacion' => 'required|integer|min:1|max:5',
            'comentario' => 'required|string|min:3|max:1000',
        ], [
            'solicitud_id.required' => 'Solo puedes calificar un servicio que hayas solicitado.',
            'calificacion.required' => 'Elige de 1 a 5 estrellas.',
            'calificacion.min' => 'Elige de 1 a 5 estrellas.',
            'calificacion.max' => 'Elige de 1 a 5 estrellas.',
            'comentario.required' => 'Cuéntanos cómo te fue con el servicio.',
            'comentario.min' => 'Cuéntanos un poco más sobre el servicio.',
        ]);

        // La solicitud debe ser de este cliente, con este proveedor y estar completada
        $solicitud = SolicitudServicio::where('id', $datos['solicitud_id'])
            ->where('cliente_id', $cliente->id)
            ->first();

        if (!$solicitud || $solicitud->proveedor_id !== $datos['proveedor_id']) {
            return response()->json(['mensaje' => 'No encontramos ese servicio entre tus solicitudes.'], 404);
        }
        if ($solicitud->estado !== 'completada') {
            return response()->json([
                'mensaje' => 'Podrás calificar al proveedor cuando el servicio esté completado.',
            ], 422);
        }

        // Una calificación por servicio (también la garantiza el índice único de solicitud_id)
        if (Resena::where('solicitud_id', $solicitud->id)->exists()) {
            return response()->json(['mensaje' => 'Ya calificaste este servicio.'], 409);
        }

        try {
            $resena = Resena::create([
                'cliente_id' => $cliente->id,
                'proveedor_id' => $solicitud->proveedor_id,
                'solicitud_id' => $solicitud->id,
                'calificacion' => $datos['calificacion'],
                'comentario' => trim($datos['comentario']),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return response()->json(['mensaje' => 'Ya calificaste este servicio.'], 409);
        }

        // Recalcular el promedio del proveedor (con detección de valores atípicos)
        CalificacionProveedor::recalcular($solicitud->proveedor_id);

        // Notificar al proveedor
        Notificacion::crear(
            $datos['proveedor_id'],
            'resena',
            'Nueva calificación recibida',
            "{$cliente->nombre} te calificó con {$datos['calificacion']} estrellas."
        );

        return response()->json(['mensaje' => 'Reseña publicada con éxito.', 'resena' => $resena->load('cliente:id,nombre,apellido')], 201);
    }

    /**
     * El proveedor responde a una reseña.
     */
    public function responderResena(Request $request, $id)
    {
        $proveedor = JWTAuth::user();

        $resena = Resena::where('id', $id)->where('proveedor_id', $proveedor->id)->first();
        if (!$resena) {
            return response()->json(['mensaje' => 'Reseña no encontrada.'], 404);
        }

        $datos = $request->validate(['respuesta' => 'required|string|max:1000']);

        $resena->update(['respuesta_proveedor' => $datos['respuesta']]);

        return response()->json(['mensaje' => 'Respuesta publicada.', 'resena' => $resena]);
    }

    /**
     * Un usuario reporta una reseña inapropiada.
     */
    public function reportarResena(Request $request, $id)
    {
        $usuario = JWTAuth::user();

        $resena = Resena::find($id);
        if (!$resena) {
            return response()->json(['mensaje' => 'Reseña no encontrada.'], 404);
        }

        $datos = $request->validate(['motivo' => 'required|string|max:255']);

        $resena->update([
            'esta_reportada' => true,
            'motivo_reporte' => $datos['motivo'],
            'reportado_por' => $usuario->id,
        ]);

        // Notificar al admin (rol_id = 3)
        $admins = \App\Models\Usuario::where('rol_id', 3)->get();
        foreach ($admins as $admin) {
            Notificacion::crear(
                $admin->id,
                'sistema',
                'Reseña reportada',
                "Una reseña fue reportada por: {$datos['motivo']}"
            );
        }

        return response()->json(['mensaje' => 'Reseña reportada. El administrador la revisará.']);
    }

    /**
     * Admin: lista todas las reseñas reportadas.
     */
    public function listarReportadas()
    {
        $admin = JWTAuth::user();
        if (!$admin || $admin->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }

        $resenas = Resena::where('esta_reportada', true)
            ->with(['cliente:id,nombre,apellido', 'proveedor:id,nombre,apellido'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($resenas);
    }

    /**
     * Admin: oculta o restaura una reseña.
     */
    public function moderarResena(Request $request, $id)
    {
        $admin = JWTAuth::user();
        if (!$admin || $admin->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }

        $resena = Resena::find($id);
        if (!$resena) {
            return response()->json(['mensaje' => 'Reseña no encontrada.'], 404);
        }

        $datos = $request->validate(['accion' => 'required|in:ocultar,restaurar']);

        $resena->update([
            'esta_ocultada' => $datos['accion'] === 'ocultar',
            'esta_reportada' => false,
        ]);

        CalificacionProveedor::recalcular($resena->proveedor_id);

        return response()->json(['mensaje' => $datos['accion'] === 'ocultar' ? 'Reseña ocultada.' : 'Reseña restaurada.']);
    }

    /**
     * Admin: elimina definitivamente una reseña reportada.
     */
    public function eliminarResena($id)
    {
        $admin = JWTAuth::user();
        if (!$admin || $admin->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }

        $resena = Resena::find($id);
        if (!$resena) {
            return response()->json(['mensaje' => 'Reseña no encontrada.'], 404);
        }

        $proveedorId = $resena->proveedor_id;
        $resena->delete();
        CalificacionProveedor::recalcular($proveedorId);

        return response()->json(['mensaje' => 'Reseña eliminada definitivamente.']);
    }

    /**
     * Admin: reseñas atípicas (método IQR) con el contexto para "investigar el origen":
     * el comentario, la solicitud y la distribución de calificaciones del proveedor.
     * ?estado=pendientes (por defecto) | revisadas | todas
     */
    public function listarAtipicas(Request $request)
    {
        $admin = JWTAuth::user();
        if (!$admin || $admin->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }

        $estado = $request->query('estado', 'pendientes');
        $consulta = Resena::where('es_atipica', true)
            ->where('esta_ocultada', false)
            ->with([
                'cliente:id,nombre,apellido',
                'proveedor:id,nombre,apellido',
                'solicitud:id,descripcion,fecha,estado,confirmacion_cliente,queja_cliente',
            ])
            ->orderByDesc('created_at');

        if ($estado === 'pendientes') {
            $consulta->whereNull('revision_atipica');
        } elseif ($estado === 'revisadas') {
            $consulta->whereNotNull('revision_atipica');
        }

        $resenas = $consulta->get();

        // Distribución de cada proveedor involucrado (para el diagrama de caja)
        $distribuciones = [];
        foreach ($resenas->pluck('proveedor_id')->unique() as $proveedorId) {
            $valores = Resena::where('proveedor_id', $proveedorId)
                ->where('esta_ocultada', false)
                ->pluck('calificacion')
                ->map(fn ($v) => (float) $v)
                ->all();
            $distribuciones[$proveedorId] = CalificacionProveedor::limites($valores);
        }

        return response()->json($resenas->map(fn ($r) => $r->toArray() + [
            'distribucion' => $distribuciones[$r->proveedor_id] ?? null,
        ])->values());
    }

    /**
     * Admin: decide qué hacer con una reseña atípica tras revisarla.
     * - conservar: fue un fallo real y crítico del servicio, vuelve a contar en el promedio.
     * - excluir: fue un error o un cliente "hater", sigue fuera del promedio (no se borra).
     */
    public function revisarAtipica(Request $request, $id)
    {
        $admin = JWTAuth::user();
        if (!$admin || $admin->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }

        $datos = $request->validate(['decision' => 'required|in:conservar,excluir']);

        $resena = Resena::find($id);
        if (!$resena) {
            return response()->json(['mensaje' => 'Reseña no encontrada.'], 404);
        }

        $resena->update([
            'revision_atipica' => $datos['decision'],
            'revisada_por' => $admin->id,
            'revisada_en' => Carbon::now(),
        ]);
        CalificacionProveedor::recalcular($resena->proveedor_id);

        return response()->json([
            'mensaje' => $datos['decision'] === 'conservar'
                ? 'La calificación vuelve a contar en el promedio del proveedor.'
                : 'La calificación queda fuera del promedio del proveedor.',
            'resena' => $resena->fresh(),
        ]);
    }
}
