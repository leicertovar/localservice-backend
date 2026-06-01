<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Usuario;
use App\Models\PerfilProveedor;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProveedorController extends Controller
{
    /**
     * Obtiene el listado de todos los proveedores registrados.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function listarProveedores()
    {
        // Obtener todos los usuarios con rol de proveedor (rol_id = 2) y su perfil
        $proveedores = Usuario::where('rol_id', 2)
            ->with('perfilProveedor')
            ->get();

        // Mapear al formato que espera el frontend
        $respuesta = $proveedores->map(function($p) {
            $perfil = $p->perfilProveedor;
            return [
                'id' => $p->id,
                'name' => trim("{$p->nombre} {$p->apellido}"),
                'category' => $perfil?->categoria_servicio ?? 'General',
                'rating' => (float)($perfil?->calificacion ?? 5.0),
                'reviews' => (int)($perfil?->total_resenas ?? 0),
                'price' => $perfil?->precio_por_hora ? "$" . number_format($perfil->precio_por_hora, 0, ',', '.') . "/hora" : 'Precio base',
                'location' => $perfil?->ciudad ?? 'Buenaventura',
                'available' => true
            ];
        });

        return response()->json($respuesta);
    }

    /**
     * Obtiene el perfil público de un proveedor por su ID, junto con sus servicios activos.
     * 
     * @param string $id Identificador del usuario proveedor.
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerPerfilProveedor($id)
    {
        // Buscar el usuario con su perfil de proveedor
        $proveedor = Usuario::with('perfilProveedor')->find($id);

        // Validar que el usuario exista y tenga el rol de proveedor (rol_id = 2)
        if (!$proveedor || $proveedor->rol_id !== 2) {
            return response()->json([
                'mensaje' => 'Proveedor no encontrado.'
            ], 404);
        }

        // Obtener los servicios activos asociados a este proveedor
        $servicios = $proveedor->id ? \App\Models\Servicio::where('usuario_id', $proveedor->id)
            ->where('esta_activo', true)
            ->get() : [];

        return response()->json([
            'id' => $proveedor->id,
            'nombre' => $proveedor->nombre,
            'apellido' => $proveedor->apellido,
            'email' => $proveedor->email,
            'telefono' => $proveedor->telefono,
            'perfil_proveedor' => $proveedor->perfilProveedor,
            'servicios' => $servicios
        ]);
    }

    /**
     * Actualiza el perfil del proveedor autenticado.
     * 
     * @param \Illuminate\Http\Request $request Petición HTTP con los datos a actualizar.
     * @return \Illuminate\Http\JsonResponse
     */
    public function actualizarPerfilProveedor(Request $request)
    {
        // Obtener el usuario proveedor actualmente autenticado
        $usuario = JWTAuth::user();

        if (!$usuario || $usuario->rol_id !== 2) {
            return response()->json([
                'mensaje' => 'No autorizado o no es un proveedor.'
            ], 401);
        }

        // Validar los datos de entrada
        $datosValidados = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:255|unique:usuarios,telefono,' . $usuario->id,
            'email' => 'required|email|max:255|unique:usuarios,email,' . $usuario->id,
            'biografia' => 'nullable|string',
            'anios_experiencia' => 'nullable|integer|min:0',
            'precio_por_hora' => 'nullable|numeric|min:0',
            'habilidades' => 'nullable|string',
            'enlaces_portafolio' => 'nullable|string',
            'horario_atencion' => 'nullable',
            'ciudad' => 'nullable|string',
            'categoria_servicio' => 'nullable|string',
            'foto_perfil' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        // Actualizar datos de la tabla 'usuarios'
        $usuario->update([
            'nombre' => $datosValidados['nombre'],
            'apellido' => $datosValidados['apellido'] ?? null,
            'telefono' => $datosValidados['telefono'] ?? null,
            'email' => $datosValidados['email'],
        ]);

        $datosPerfil = [
            'biografia' => $datosValidados['biografia'] ?? null,
            'anios_experiencia' => $datosValidados['anios_experiencia'] ?? 0,
            'precio_por_hora' => $datosValidados['precio_por_hora'] ?? 0.00,
            'habilidades' => $datosValidados['habilidades'] ?? null,
            'enlaces_portafolio' => $datosValidados['enlaces_portafolio'] ?? null,
            'horario_atencion' => $datosValidados['horario_atencion'] ?? null,
            'ciudad' => $datosValidados['ciudad'] ?? null,
            'categoria_servicio' => $datosValidados['categoria_servicio'] ?? $usuario->perfilProveedor?->categoria_servicio,
        ];

        if ($request->hasFile('foto_perfil')) {
            $archivo = $request->file('foto_perfil');
            $nombre = time() . '_' . preg_replace('/\s+/', '_', $archivo->getClientOriginalName());
            $ruta = $archivo->storeAs('fotos_perfil', $nombre, 'public');
            $datosPerfil['foto_perfil'] = '/storage/' . $ruta;
        }

        // Actualizar o crear datos de la tabla 'perfiles_proveedor'
        $perfil = PerfilProveedor::updateOrCreate(
            ['usuario_id' => $usuario->id],
            $datosPerfil
        );

        // Volver a cargar las relaciones del usuario
        $usuario->load(['rol', 'perfilProveedor']);

        return response()->json([
            'mensaje' => 'Perfil de proveedor actualizado correctamente.',
            'usuario' => $usuario
        ]);
    }
}
