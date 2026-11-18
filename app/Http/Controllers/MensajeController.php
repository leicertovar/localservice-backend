<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mensaje;
use App\Models\Notificacion;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

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
            // Solo fotos, videos y documentos comunes: nada que el servidor pueda ejecutar o
            // servir como página (.php, .html, .svg…). Máximo 20 MB.
            'archivo' => 'nullable|file|max:20480|mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm,pdf,doc,docx,xls,xlsx,txt',
        ], [
            'contenido.max' => 'El mensaje puede tener máximo 2000 caracteres.',
            'archivo.max' => 'El archivo puede pesar máximo 20 MB.',
            'archivo.mimes' => 'Puedes enviar fotos, videos, PDF, Word, Excel o texto.',
        ]);
        if ($datos['receptor_id'] === $usuario->id) {
            return response()->json(['mensaje' => 'No puedes enviarte mensajes a ti mismo.'], 422);
        }

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

            // El nombre original solo se muestra; en el disco se guarda con un nombre aleatorio
            $nombreArchivo = mb_substr($archivo->getClientOriginalName(), 0, 150);
            $extension = strtolower($archivo->guessExtension() ?: $archivo->getClientOriginalExtension());
            $ruta = $archivo->storeAs('chat', Str::uuid() . '.' . $extension, 'public');
            $urlArchivo = '/storage/' . $ruta;
        }

        $contenido = isset($datos['contenido']) ? trim($datos['contenido']) : null;

        if (!$contenido && !$urlArchivo) {
            return response()->json(['mensaje' => 'Debes enviar texto o un archivo.'], 422);
        }

        $mensaje = Mensaje::create([
            'emisor_id' => $usuario->id,
            'receptor_id' => $datos['receptor_id'],
            'contenido' => $contenido,
            'tipo' => $tipo,
            'url_archivo' => $urlArchivo,
            'nombre_archivo' => $nombreArchivo,
        ]);

        // Notificar al receptor
        Notificacion::crear(
            $datos['receptor_id'],
            'mensaje',
            'Nuevo mensaje',
            "{$usuario->nombre}: " . ($contenido ? mb_substr($contenido, 0, 80) : "📎 Archivo adjunto"),
            "chat:{$usuario->id}"
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
