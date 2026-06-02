<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Resena;
use App\Models\PerfilProveedor;
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
            'solicitud_id' => 'nullable|exists:solicitudes_servicio,id',
            'calificacion' => 'required|integer|min:1|max:5',
            'comentario' => 'required|string|max:1000',
        ]);

        // Evitar reseñas duplicadas para la misma solicitud
        if (!empty($datos['solicitud_id'])) {
            $existe = Resena::where('cliente_id', $cliente->id)
                ->where('solicitud_id', $datos['solicitud_id'])
                ->first();
            if ($existe) {
                return response()->json(['mensaje' => 'Ya dejaste una reseña para este servicio.'], 409);
            }
        }

        $resena = Resena::create([
            'cliente_id' => $cliente->id,
            'proveedor_id' => $datos['proveedor_id'],
            'solicitud_id' => $datos['solicitud_id'] ?? null,
            'calificacion' => $datos['calificacion'],
            'comentario' => $datos['comentario'],
        ]);

        // Recalcular calificación promedio del proveedor
        $this->recalcularCalificacion($datos['proveedor_id']);

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

        if ($datos['accion'] === 'ocultar') {
            $this->recalcularCalificacion($resena->proveedor_id);
        } else {
            $this->recalcularCalificacion($resena->proveedor_id);
        }

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
        $this->recalcularCalificacion($proveedorId);

        return response()->json(['mensaje' => 'Reseña eliminada definitivamente.']);
    }

    /**
     * Recalcula la calificación promedio del proveedor.
     */
    private function recalcularCalificacion(string $proveedorId): void
    {
        $datos = Resena::where('proveedor_id', $proveedorId)
            ->where('esta_ocultada', false)
            ->selectRaw('AVG(calificacion) as promedio, COUNT(*) as total')
            ->first();

        PerfilProveedor::where('usuario_id', $proveedorId)->update([
            'calificacion' => round($datos->promedio ?? 0, 2),
            'total_resenas' => $datos->total ?? 0,
        ]);
    }
}
