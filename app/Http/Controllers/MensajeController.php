<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mensaje;
use App\Models\Notificacion;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Events\MensajeActualizado;
use App\Events\MensajeEnviado;

class MensajeController extends Controller
{
    /**
     * Obtiene el historial de mensajes entre el usuario autenticado y otro usuario.
     */
    public function obtenerConversacion($otroUsuarioId)
    {
        $usuario = JWTAuth::user();

        $mensajes = Mensaje::where(function ($q) use ($usuario, $otroUsuarioId) {
            $q->where(function ($q) use ($usuario, $otroUsuarioId) {
                $q->where('emisor_id', $usuario->id)->where('receptor_id', $otroUsuarioId);
            })->orWhere(function ($q) use ($usuario, $otroUsuarioId) {
                $q->where('emisor_id', $otroUsuarioId)->where('receptor_id', $usuario->id);
            });
        })
        // Sin los que el usuario eliminó "para mí"
        ->visiblesPara($usuario->id)
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

        // Tiempo real: si Reverb no responde, el mensaje ya quedó guardado y los clientes
        // lo reciben con la consulta periódica de respaldo.
        try {
            broadcast(new MensajeEnviado($mensaje));
        } catch (\Throwable $e) {
            Log::warning('No se pudo emitir el mensaje en tiempo real: ' . $e->getMessage());
        }

        return response()->json($mensaje->load('emisor:id,nombre,apellido'), 201);
    }

    /**
     * Lista todos los contactos con los que el usuario tiene conversaciones.
     */
    public function listarConversaciones()
    {
        $usuario = JWTAuth::user();

        $conversaciones = Mensaje::where(fn ($q) => $q->where('emisor_id', $usuario->id)->orWhere('receptor_id', $usuario->id))
            ->visiblesPara($usuario->id)
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

    /**
     * El emisor edita el texto de su mensaje (hasta 5 minutos después de enviarlo).
     */
    public function editarMensaje(Request $request, $id)
    {
        $usuario = JWTAuth::user();
        $mensaje = Mensaje::find($id);

        if (!$mensaje || $mensaje->emisor_id !== $usuario->id || $mensaje->oculto_emisor) {
            return response()->json(['mensaje' => 'Mensaje no encontrado.'], 404);
        }
        $datos = $request->validate([
            'contenido' => 'required|string|max:2000',
        ], [
            'contenido.required' => 'Escribe el nuevo texto del mensaje.',
            'contenido.max' => 'El mensaje puede tener máximo 2000 caracteres.',
        ]);
        if ($mensaje->eliminado_en) {
            return response()->json(['mensaje' => 'Este mensaje ya fue eliminado.'], 422);
        }
        if ($mensaje->contenido === null || $mensaje->contenido === '') {
            return response()->json(['mensaje' => 'Solo se puede editar el texto de un mensaje.'], 422);
        }
        if ($mensaje->created_at->lt(now()->subMinutes(Mensaje::MINUTOS_EDITAR))) {
            return response()->json([
                'mensaje' => 'Solo puedes editar un mensaje durante los ' . Mensaje::MINUTOS_EDITAR . ' minutos siguientes a enviarlo.',
            ], 422);
        }

        $nuevo = trim($datos['contenido']);
        if ($nuevo === '') {
            return response()->json(['mensaje' => 'Escribe el nuevo texto del mensaje.'], 422);
        }
        if ($nuevo !== $mensaje->contenido) {
            $mensaje->update(['contenido' => $nuevo, 'editado_en' => now()]);
            // La notificación del receptor muestra el texto nuevo
            $this->notificacionDe($mensaje)?->update(['descripcion' => "{$usuario->nombre}: " . mb_substr($nuevo, 0, 80)]);
            $this->emitir(new MensajeActualizado($mensaje, [$mensaje->emisor_id, $mensaje->receptor_id]));
        }

        return response()->json($mensaje->load('emisor:id,nombre,apellido'));
    }

    /**
     * Elimina un mensaje:
     * - para=mi: desaparece solo del chat de quien lo elimina (el otro lo sigue viendo);
     * - para=todos: solo el emisor y hasta 1 minuto después de enviarlo; los dos ven "Se eliminó este mensaje".
     */
    public function eliminarMensaje(Request $request, $id)
    {
        $usuario = JWTAuth::user();
        $mensaje = Mensaje::find($id);
        $esEmisor = $mensaje && $mensaje->emisor_id === $usuario->id;
        $esReceptor = $mensaje && $mensaje->receptor_id === $usuario->id;

        if (!$mensaje || (!$esEmisor && !$esReceptor) || ($esEmisor && $mensaje->oculto_emisor) || ($esReceptor && $mensaje->oculto_receptor)) {
            return response()->json(['mensaje' => 'Mensaje no encontrado.'], 404);
        }
        $para = $request->query('para', $request->input('para', 'mi'));

        if ($para === 'todos') {
            if (!$esEmisor) {
                return response()->json(['mensaje' => 'Solo quien envió el mensaje puede eliminarlo para todos.'], 403);
            }
            if ($mensaje->eliminado_en) {
                return response()->json($mensaje);
            }
            // 1 minuto (+5 s de margen por la demora de la red)
            if ($mensaje->created_at->lt(now()->subSeconds(Mensaje::SEGUNDOS_ELIMINAR_PARA_TODOS + 5))) {
                return response()->json([
                    'mensaje' => 'Solo puedes eliminar un mensaje para todos durante el primer minuto. Puedes eliminarlo para ti.',
                ], 422);
            }
            if ($mensaje->url_archivo && str_starts_with($mensaje->url_archivo, '/storage/')) {
                Storage::disk('public')->delete(substr($mensaje->url_archivo, strlen('/storage/')));
            }
            // Tampoco debe quedar el texto en la notificación del receptor
            $this->notificacionDe($mensaje)?->delete();
            $mensaje->update([
                'eliminado_en' => now(),
                'contenido' => null,
                'url_archivo' => null,
                'nombre_archivo' => null,
                'leido' => true,
            ]);
            $this->emitir(new MensajeActualizado($mensaje, [$mensaje->emisor_id, $mensaje->receptor_id]));
            return response()->json($mensaje->load('emisor:id,nombre,apellido'));
        }

        $mensaje->update($esEmisor ? ['oculto_emisor' => true] : ['oculto_receptor' => true, 'leido' => true]);
        // Solo a los otros dispositivos de quien lo eliminó
        $this->emitir(new MensajeActualizado($mensaje, [$usuario->id]));
        return response()->json(['mensaje' => 'Mensaje eliminado de tu chat.']);
    }

    /** Notificación "Nuevo mensaje" que generó este mensaje (se creó en la misma petición). */
    private function notificacionDe(Mensaje $mensaje): ?Notificacion
    {
        return Notificacion::where('usuario_id', $mensaje->receptor_id)
            ->where('tipo', 'mensaje')
            ->where('enlace', "chat:{$mensaje->emisor_id}")
            ->whereBetween('created_at', [$mensaje->created_at->copy()->subSeconds(2), $mensaje->created_at->copy()->addSeconds(10)])
            ->orderBy('id')
            ->first();
    }

    /** Si Reverb no responde, el cambio ya quedó guardado y llega con la consulta de respaldo. */
    private function emitir($evento): void
    {
        try {
            broadcast($evento);
        } catch (\Throwable $e) {
            Log::warning('No se pudo emitir el cambio del mensaje en tiempo real: ' . $e->getMessage());
        }
    }
}
