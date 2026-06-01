<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mensaje;
use App\Models\Notificacion;
use Tymon\JWTAuth\Facades\JWTAuth;

class MensajeController extends Controller
{
    /**
     * Obtiene el historial de mensajes entre el usuario autenticado y otro usuario.
     */
    public function obtenerConversacion($otroUsuarioId)
    {
        $usuario = JWTAuth::user();

        $mensajes = Mensaje::where(function ($q) use ($usuario, $otroUsuarioId) {
            $q->where('emisor_id', $usuario->id)->where('receptor_id', $otroUsuarioId);
        })->orWhere(function ($q) use ($usuario, $otroUsuarioId) {
            $q->where('emisor_id', $otroUsuarioId)->where('receptor_id', $usuario->id);
        })
        ->with('emisor:id,nombre,apellido')
        ->orderBy('created_at', 'asc')
        ->get();

        // Marcar como leídos los mensajes recibidos
        Mensaje::where('emisor_id', $otroUsuarioId)
            ->where('receptor_id', $usuario->id)
            ->where('leido', false)
            ->update(['leido' => true]);

        return response()->json($mensajes);
    }

    /**
     * Envía un mensaje de texto o archivo al receptor.
     */
    public function enviarMensaje(Request $request)
    {
        $usuario = JWTAuth::user();

        $datos = $request->validate([
            'receptor_id' => 'required|exists:usuarios,id',
            'contenido' => 'nullable|string|max:2000',
            'archivo' => 'nullable|file|max:20480', // 20MB
        ]);

        $tipo = 'texto';
        $urlArchivo = null;
        $nombreArchivo = null;

        if ($request->hasFile('archivo')) {
            $archivo = $request->file('archivo');
            $mime = $archivo->getMimeType();

            if (str_starts_with($mime, 'image/')) {
                $tipo = 'imagen';
            } elseif (str_starts_with($mime, 'video/')) {
                $tipo = 'video';
            } else {
                $tipo = 'documento';
            }

            $nombreArchivo = $archivo->getClientOriginalName();
            $ruta = $archivo->storeAs('chat', time() . '_' . preg_replace('/\s+/', '_', $nombreArchivo), 'public');
            $urlArchivo = '/storage/' . $ruta;
        }

        if (!$datos['contenido'] && !$urlArchivo) {
            return response()->json(['mensaje' => 'Debes enviar texto o un archivo.'], 422);
        }

        $mensaje = Mensaje::create([
            'emisor_id' => $usuario->id,
            'receptor_id' => $datos['receptor_id'],
            'contenido' => $datos['contenido'] ?? null,
            'tipo' => $tipo,
            'url_archivo' => $urlArchivo,
            'nombre_archivo' => $nombreArchivo,
        ]);

        // Notificar al receptor
        Notificacion::crear(
            $datos['receptor_id'],
            'mensaje',
            'Nuevo mensaje',
            "{$usuario->nombre}: " . ($datos['contenido'] ? substr($datos['contenido'], 0, 80) : "📎 Archivo adjunto")
        );

        return response()->json($mensaje->load('emisor:id,nombre,apellido'), 201);
    }

    /**
     * Lista todos los contactos con los que el usuario tiene conversaciones.
     */
    public function listarConversaciones()
    {
        $usuario = JWTAuth::user();

        $conversaciones = Mensaje::where('emisor_id', $usuario->id)
            ->orWhere('receptor_id', $usuario->id)
            ->with(['emisor:id,nombre,apellido', 'receptor:id,nombre,apellido'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($m) use ($usuario) {
                return $m->emisor_id === $usuario->id ? $m->receptor_id : $m->emisor_id;
            })
            ->map(function ($msgs) use ($usuario) {
                $ultimo = $msgs->first();
                $otroUsuario = $ultimo->emisor_id === $usuario->id ? $ultimo->receptor : $ultimo->emisor;
                $noLeidos = $msgs->where('receptor_id', $usuario->id)->where('leido', false)->count();
                return [
                    'usuario' => $otroUsuario,
                    'ultimo_mensaje' => $ultimo,
                    'no_leidos' => $noLeidos,
                ];
            })
            ->values();

        return response()->json($conversaciones);
    }
}
