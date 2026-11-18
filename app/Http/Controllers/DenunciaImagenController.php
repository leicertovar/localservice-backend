<?php

namespace App\Http\Controllers;

use App\Models\DenunciaImagen;
use App\Models\Notificacion;
use App\Models\ResenaImagen;
use App\Models\ServicioImagen;
use App\Models\Usuario;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Denuncias de fotos de reseñas y de servicios. Cualquier usuario denuncia; el administrador
 * revisa la foto con su contexto y decide ocultarla o mantenerla.
 */
class DenunciaImagenController extends Controller
{
    private const TIPOS = ['resena', 'servicio'];

    /** Foto denunciable según su tipo, o null si no existe. */
    private function buscarImagen(string $tipo, $id): ResenaImagen|ServicioImagen|null
    {
        return $tipo === 'resena' ? ResenaImagen::find($id) : ServicioImagen::find($id);
    }

    /**
     * POST /imagenes/{tipo}/{id}/denunciar  { motivo }
     */
    public function denunciar(Request $request, string $tipo, $id)
    {
        $usuario = JWTAuth::user();
        if (!$usuario) {
            return response()->json(['mensaje' => 'No autorizado.'], 401);
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            return response()->json(['mensaje' => 'Tipo de foto no válido.'], 404);
        }

        $imagen = $this->buscarImagen($tipo, $id);
        if (!$imagen || $imagen->esta_ocultada) {
            return response()->json(['mensaje' => 'Esta foto ya no está disponible.'], 404);
        }

        $datos = $request->validate(['motivo' => 'required|string|min:3|max:255'], [
            'motivo.required' => 'Cuéntanos por qué denuncias esta foto.',
            'motivo.min' => 'Cuéntanos por qué denuncias esta foto.',
        ]);

        try {
            DenunciaImagen::create([
                'tipo' => $tipo,
                'imagen_id' => $imagen->id,
                'denunciante_id' => $usuario->id,
                'motivo' => trim($datos['motivo']),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return response()->json(['mensaje' => 'Ya denunciaste esta foto. El administrador la está revisando.'], 409);
        }

        foreach (Usuario::where('rol_id', 3)->pluck('id') as $adminId) {
            Notificacion::crear(
                $adminId,
                'sistema',
                'Foto denunciada',
                ($tipo === 'resena' ? 'Una foto de una reseña' : 'Una foto de un servicio') . " fue denunciada: {$datos['motivo']}"
            );
        }

        return response()->json(['mensaje' => 'Gracias. El administrador revisará la foto.'], 201);
    }

    /**
     * Admin: fotos denunciadas, agrupadas por foto, con su contexto.
     * GET /admin/denuncias-imagenes?estado=pendientes|resueltas
     */
    public function listar(Request $request)
    {
        $admin = JWTAuth::user();
        if (!$admin || $admin->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }

        $estado = $request->query('estado') === 'resueltas' ? 'resuelta' : 'pendiente';
        $denuncias = DenunciaImagen::where('estado', $estado)
            ->with('denunciante:id,nombre,apellido')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(fn ($d) => "{$d->tipo}:{$d->imagen_id}");

        $resultado = [];
        foreach ($denuncias as $grupo) {
            $primera = $grupo->first();
            $imagen = $this->buscarImagen($primera->tipo, $primera->imagen_id);
            if (!$imagen) {
                continue; // la foto o su reseña/servicio ya se eliminó
            }

            if ($primera->tipo === 'resena') {
                $resena = $imagen->resena()->with(['cliente:id,nombre,apellido', 'proveedor:id,nombre,apellido'])->first();
                $contexto = [
                    'titulo' => 'Reseña de ' . trim(($resena?->cliente?->nombre ?? 'Cliente') . ' ' . ($resena?->cliente?->apellido ?? ''))
                        . ' para ' . trim(($resena?->proveedor?->nombre ?? 'Proveedor') . ' ' . ($resena?->proveedor?->apellido ?? '')),
                    'texto' => $resena?->comentario,
                    'calificacion' => $resena?->calificacion,
                    'dueno_id' => $resena?->cliente_id,
                ];
            } else {
                $servicio = $imagen->servicio()->with('proveedor:id,nombre,apellido')->first();
                $contexto = [
                    'titulo' => 'Servicio "' . ($servicio?->nombre ?? '') . '" de '
                        . trim(($servicio?->proveedor?->nombre ?? 'Proveedor') . ' ' . ($servicio?->proveedor?->apellido ?? '')),
                    'texto' => $servicio?->descripcion,
                    'calificacion' => null,
                    'dueno_id' => $servicio?->usuario_id,
                ];
            }

            $resultado[] = [
                'tipo' => $primera->tipo,
                'imagen_id' => $imagen->id,
                'ruta' => $imagen->ruta,
                'esta_ocultada' => $imagen->esta_ocultada,
                'contexto' => $contexto,
                'total_denuncias' => $grupo->count(),
                'decision' => $primera->decision,
                'denuncias' => $grupo->map(fn ($d) => [
                    'id' => $d->id,
                    'motivo' => $d->motivo,
                    'denunciante' => trim(($d->denunciante?->nombre ?? 'Usuario') . ' ' . ($d->denunciante?->apellido ?? '')),
                    'created_at' => $d->created_at,
                ])->values(),
                'ultima_denuncia' => $primera->created_at,
            ];
        }

        return response()->json($resultado);
    }

    /**
     * Admin: decide sobre una foto denunciada (resuelve todas sus denuncias pendientes).
     * PATCH /admin/denuncias-imagenes/{tipo}/{id}  { decision: ocultar|mantener }
     */
    public function resolver(Request $request, string $tipo, $id)
    {
        $admin = JWTAuth::user();
        if (!$admin || $admin->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            return response()->json(['mensaje' => 'Tipo de foto no válido.'], 404);
        }

        $imagen = $this->buscarImagen($tipo, $id);
        if (!$imagen) {
            return response()->json(['mensaje' => 'Foto no encontrada.'], 404);
        }

        $datos = $request->validate(['decision' => 'required|in:ocultar,mantener']);
        $ocultar = $datos['decision'] === 'ocultar';

        $imagen->update(['esta_ocultada' => $ocultar]);
        $resueltas = DenunciaImagen::where('tipo', $tipo)->where('imagen_id', $imagen->id)->where('estado', 'pendiente')
            ->update([
                'estado' => 'resuelta',
                'decision' => $datos['decision'],
                'revisada_por' => $admin->id,
                'revisada_en' => Carbon::now(),
            ]);

        // Avisar al dueño de la foto cuando se retira
        if ($ocultar) {
            $duenoId = $tipo === 'resena' ? $imagen->resena?->cliente_id : $imagen->servicio?->usuario_id;
            if ($duenoId) {
                Notificacion::crear(
                    $duenoId,
                    'sistema',
                    'Retiramos una foto',
                    $tipo === 'resena'
                        ? 'Una foto de tu reseña fue retirada porque no cumple las normas de la comunidad.'
                        : 'Una foto de tu servicio "' . ($imagen->servicio?->nombre ?? '') . '" fue retirada porque no cumple las normas de la comunidad.'
                );
            }
        }

        return response()->json([
            'mensaje' => $ocultar ? 'La foto quedó oculta.' : 'La foto se mantiene publicada.',
            'denuncias_resueltas' => $resueltas,
        ]);
    }
}
