<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Tymon\JWTAuth\Facades\JWTAuth;

class NotificacionController extends Controller
{
    /**
     * Lista las notificaciones del usuario autenticado.
     */
    public function listar()
    {
        $usuario = JWTAuth::user();

        $notificaciones = Notificacion::where('usuario_id', $usuario->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $noLeidas = $notificaciones->where('leida', false)->count();

        return response()->json([
            'notificaciones' => $notificaciones,
            'no_leidas' => $noLeidas,
        ]);
    }

    /**
     * Marca todas las notificaciones del usuario como leídas.
     */
    public function marcarTodasLeidas()
    {
        $usuario = JWTAuth::user();

        Notificacion::where('usuario_id', $usuario->id)
            ->where('leida', false)
            ->update(['leida' => true]);

        return response()->json(['mensaje' => 'Notificaciones marcadas como leídas.']);
    }

    /**
     * Marca una notificación específica como leída.
     */
    public function marcarLeida($id)
    {
        $usuario = JWTAuth::user();

        $notificacion = Notificacion::where('id', $id)
            ->where('usuario_id', $usuario->id)
            ->first();

        if (!$notificacion) {
            return response()->json(['mensaje' => 'Notificación no encontrada.'], 404);
        }

        $notificacion->update(['leida' => true]);

        return response()->json(['mensaje' => 'Notificación leída.']);
    }
}
