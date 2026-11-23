<?php

namespace App\Http\Controllers;

use App\Models\DispositivoPush;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/** Registro de los teléfonos que reciben notificaciones push. */
class DispositivoPushController extends Controller
{
    /** La app envía su token de Firebase al iniciar sesión (y cuando Firebase lo renueva). */
    public function registrar(Request $request)
    {
        $usuario = JWTAuth::user();
        $datos = $request->validate([
            'token' => 'required|string|max:512',
            'plataforma' => 'nullable|in:android',
        ]);

        // Si el teléfono tenía otra cuenta abierta, el token pasa a la cuenta actual
        DispositivoPush::updateOrCreate(
            ['token' => $datos['token']],
            ['usuario_id' => $usuario->id, 'plataforma' => $datos['plataforma'] ?? 'android']
        );

        return response()->json(['mensaje' => 'Notificaciones push activadas.']);
    }

    /** Al cerrar sesión el teléfono deja de recibir las notificaciones de esa cuenta. */
    public function eliminar(Request $request)
    {
        $usuario = JWTAuth::user();
        $datos = $request->validate(['token' => 'required|string|max:512']);

        DispositivoPush::where('token', $datos['token'])->where('usuario_id', $usuario->id)->delete();

        return response()->json(['mensaje' => 'Notificaciones push desactivadas en este teléfono.']);
    }
}
