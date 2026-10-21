<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rechaza los tokens de cuentas desactivadas o eliminadas.
 * Un JWT no se puede revocar en todos los dispositivos, así que se valida en cada petición.
 */
class CuentaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            try {
                $usuario = auth('api')->user();
            } catch (\Throwable $e) {
                $usuario = null; // token inválido o vencido: lo resuelve el guard de cada ruta
            }

            if ($usuario && ($usuario->desactivado_en || $usuario->eliminado_en)) {
                return response()->json([
                    'mensaje' => 'Esta cuenta está desactivada. Inicia sesión de nuevo para reactivarla.',
                    'cuenta_desactivada' => true,
                ], 401);
            }
        }

        return $next($request);
    }
}
