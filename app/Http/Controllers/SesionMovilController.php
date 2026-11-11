<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\SesionMovil;
use App\Models\Usuario;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * "Recuérdame" de la app móvil.
 *
 * 1. Tras iniciar sesión con "Recuérdame", la app pide un token de refresco (POST /auth/movil/sesion)
 *    y lo guarda en el almacén seguro del teléfono.
 * 2. Al abrir la app lo cambia por un JWT nuevo (POST /auth/movil/refrescar). Cada uso rota el token
 *    y renueva su vencimiento a 30 días, sin pasar nunca de 120 días desde el inicio de sesión.
 * 3. Al cerrar sesión se revoca (POST /auth/movil/cerrar-sesion).
 */
class SesionMovilController extends Controller
{
    /** Días que dura el token sin abrir la app. */
    private const DIAS_INACTIVIDAD = 30;

    /** Límite absoluto desde el inicio de sesión: después hay que volver a entrar. */
    private const DIAS_MAXIMOS = 120;

    /** Sesiones recordadas simultáneas por usuario (p. ej. varios teléfonos). */
    private const MAX_SESIONES = 10;

    /**
     * Si un token ya rotado se vuelve a usar pasados estos segundos, se asume robo y se revoca
     * toda la familia. Dentro de este margen se trata como una petición duplicada de la app.
     */
    private const MARGEN_REUSO_SEGUNDOS = 15;

    /**
     * Crea la sesión recordada del usuario autenticado y devuelve su token de refresco.
     */
    public function crear(Request $request)
    {
        $usuario = JWTAuth::user();
        $datos = $request->validate(['dispositivo' => 'nullable|string|max:120']);

        // Solo clientes y proveedores usan la app; el administrador trabaja en la web
        if (!$usuario || !in_array($usuario->rol_id, [1, 2], true)) {
            return response()->json(['mensaje' => 'Esta cuenta no puede recordar la sesión en la app.'], 403);
        }

        $this->limitarSesionesActivas($usuario->id);

        $ahora = Carbon::now();
        [$sesion, $token] = $this->emitir(
            $usuario->id,
            (string) Str::uuid(),
            $datos['dispositivo'] ?? null,
            $ahora->copy()->addDays(self::DIAS_MAXIMOS)
        );

        return response()->json($this->respuestaToken($sesion, $token), 201);
    }

    /**
     * Cambia un token de refresco válido por un JWT nuevo y otro token de refresco.
     */
    public function refrescar(Request $request)
    {
        $datos = $request->validate(['refresh_token' => 'required|string|max:200']);
        $sesion = SesionMovil::where('token_hash', SesionMovil::hashDe($datos['refresh_token']))->first();
        $ahora = Carbon::now();

        if (!$sesion) {
            return $this->sesionInvalida();
        }

        // Token ya usado: dentro del margen es un reintento de la app; después, posible robo.
        if ($sesion->revocado_en) {
            $reuso = $sesion->motivo_revocacion === 'rotado'
                && $sesion->revocado_en->diffInSeconds($ahora) > self::MARGEN_REUSO_SEGUNDOS;
            if ($reuso) {
                SesionMovil::where('familia', $sesion->familia)
                    ->whereNull('revocado_en')
                    ->update(['revocado_en' => $ahora, 'motivo_revocacion' => 'reutilizado']);
            }
            return $this->sesionInvalida();
        }

        if ($sesion->expira_en->isPast() || $sesion->caduca_en->isPast()) {
            $sesion->update(['revocado_en' => $ahora, 'motivo_revocacion' => 'vencido']);
            return $this->sesionInvalida('Tu sesión venció. Inicia sesión de nuevo.');
        }

        $usuario = Usuario::find($sesion->usuario_id);
        if (!$usuario || $usuario->desactivado_en || $usuario->eliminado_en) {
            SesionMovil::revocarDeUsuario($sesion->usuario_id, 'cuenta_inactiva');
            return $this->sesionInvalida();
        }

        // Mismo control que el login: un proveedor pendiente no puede entrar
        if (!$usuario->esta_aprobado) {
            return response()->json([
                'mensaje' => 'Su cuenta aún no ha sido aprobada por el administrador.',
            ], 403);
        }

        [$nueva, $token] = DB::transaction(function () use ($sesion, $ahora) {
            $sesion->update([
                'revocado_en' => $ahora,
                'motivo_revocacion' => 'rotado',
                'ultimo_uso_en' => $ahora,
            ]);
            return $this->emitir($sesion->usuario_id, $sesion->familia, $sesion->dispositivo, $sesion->caduca_en);
        });

        $usuario->load(['rol', 'perfilCliente', 'perfilProveedor']);

        return response()->json([
            'token_acceso' => JWTAuth::fromUser($usuario),
            'tipo_token' => 'bearer',
            'usuario' => $usuario,
        ] + $this->respuestaToken($nueva, $token));
    }

    /**
     * Revoca el token de refresco de este teléfono (cerrar sesión). Siempre responde 200.
     */
    public function cerrar(Request $request)
    {
        $datos = $request->validate(['refresh_token' => 'required|string|max:200']);

        SesionMovil::where('token_hash', SesionMovil::hashDe($datos['refresh_token']))
            ->whereNull('revocado_en')
            ->update(['revocado_en' => Carbon::now(), 'motivo_revocacion' => 'cerrada']);

        return response()->json(['mensaje' => 'Sesión cerrada en este dispositivo.']);
    }

    /**
     * Crea un token de refresco: vence en 30 días o al llegar al límite absoluto, lo que ocurra primero.
     *
     * @return array{0: SesionMovil, 1: string}
     */
    private function emitir(string $usuarioId, string $familia, ?string $dispositivo, Carbon $caducaEn): array
    {
        $token = Str::random(64);
        $expiraEn = Carbon::now()->addDays(self::DIAS_INACTIVIDAD);

        $sesion = SesionMovil::create([
            'usuario_id' => $usuarioId,
            'familia' => $familia,
            'token_hash' => SesionMovil::hashDe($token),
            'dispositivo' => $dispositivo,
            'expira_en' => $expiraEn->lessThan($caducaEn) ? $expiraEn : $caducaEn,
            'caduca_en' => $caducaEn,
        ]);

        return [$sesion, $token];
    }

    /** Revoca las sesiones más antiguas si el usuario llegó al máximo permitido. */
    private function limitarSesionesActivas(string $usuarioId): void
    {
        $activas = SesionMovil::where('usuario_id', $usuarioId)
            ->whereNull('revocado_en')
            ->orderByDesc('created_at')
            ->pluck('id');

        $sobrantes = $activas->slice(self::MAX_SESIONES - 1);
        if ($sobrantes->isNotEmpty()) {
            SesionMovil::whereIn('id', $sobrantes)
                ->update(['revocado_en' => Carbon::now(), 'motivo_revocacion' => 'limite']);
        }
    }

    private function respuestaToken(SesionMovil $sesion, string $token): array
    {
        return [
            'refresh_token' => $token,
            'refresh_expira_en' => $sesion->expira_en->toIso8601String(),
            'refresh_caduca_en' => $sesion->caduca_en->toIso8601String(),
        ];
    }

    private function sesionInvalida(string $mensaje = 'Tu sesión ya no es válida. Inicia sesión de nuevo.')
    {
        return response()->json(['mensaje' => $mensaje, 'sesion_invalida' => true], 401);
    }
}
