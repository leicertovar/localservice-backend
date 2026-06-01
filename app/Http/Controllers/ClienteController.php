<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PerfilCliente;
use Tymon\JWTAuth\Facades\JWTAuth;

class ClienteController extends Controller
{
    /**
     * Actualiza el perfil del cliente autenticado, incluyendo foto de perfil opcional.
     */
    public function actualizarPerfil(Request $request)
    {
        $usuario = JWTAuth::user();

        if (!$usuario || $usuario->rol_id !== 1) {
            return response()->json(['mensaje' => 'No autorizado.'], 401);
        }

        $datos = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:255|unique:usuarios,telefono,' . $usuario->id,
            'email' => 'required|email|max:255|unique:usuarios,email,' . $usuario->id,
            'direccion' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:255',
            'foto_perfil' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $usuario->update([
            'nombre' => $datos['nombre'],
            'apellido' => $datos['apellido'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
            'email' => $datos['email'],
        ]);

        $datosPerfil = [
            'direccion' => $datos['direccion'] ?? null,
            'ciudad' => $datos['ciudad'] ?? null,
        ];

        if ($request->hasFile('foto_perfil')) {
            $archivo = $request->file('foto_perfil');
            $nombre = time() . '_' . preg_replace('/\s+/', '_', $archivo->getClientOriginalName());
            $ruta = $archivo->storeAs('fotos_perfil', $nombre, 'public');
            $datosPerfil['foto_perfil'] = '/storage/' . $ruta;
        }

        PerfilCliente::updateOrCreate(
            ['usuario_id' => $usuario->id],
            $datosPerfil
        );

        $usuario->load(['rol', 'perfilCliente']);

        return response()->json([
            'mensaje' => 'Perfil actualizado correctamente.',
            'usuario' => $usuario,
        ]);
    }
}
