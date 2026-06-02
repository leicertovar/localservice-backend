<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Models\Usuario;
use App\Models\Rol;
use App\Models\PerfilCliente;
use App\Models\PerfilProveedor;
use App\Mail\ProveedorRegistradoMail;
use App\Mail\ProveedorAprobadoMail;
use App\Mail\ProveedorRechazadoMail;
use App\Mail\RestablecerPasswordMail;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    /**
     * Registra un nuevo usuario (Cliente o Proveedor) en la plataforma.
     * Crea de forma automática y asíncrona sus perfiles asociados en español.
     */
    public function registrar(Request $request)
    {
        // 1. Validar los datos de entrada según las reglas específicas de cada rol
        $datosValidados = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'email' => 'required|email|unique:usuarios,email',
            'telefono' => 'nullable|string|unique:usuarios,telefono',
            'password' => 'required|string|min:8|confirmed',
            'rol' => 'nullable|in:cliente,proveedor,admin',
            // Reglas obligatorias para Proveedor
            'categoria_servicio' => 'required_if:rol,proveedor|string|max:255',
            'doc_cedula'  => 'required_if:rol,proveedor|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'doc_rut'     => 'required_if:rol,proveedor|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'doc_diploma' => 'required_if:rol,proveedor|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        // 2. Mapear el rol del formato string recibido al ID numérico relacional
        $nombreRol = $datosValidados['rol'] ?? 'cliente';
        $rolId = 1; // ID 1 representa al Cliente
        if ($nombreRol === 'proveedor') {
            $rolId = 2; // ID 2 representa al Proveedor
        } elseif ($nombreRol === 'admin') {
            $rolId = 3; // ID 3 representa al Administrador
        }

        // 3. Evaluar estado de aprobación inicial:
        // Los proveedores requieren validación de documentos física por el admin (registran en falso)
        // Los clientes y administradores se aprueban por defecto de forma directa (registran en verdadero)
        $estaAprobado = ($nombreRol !== 'proveedor');

        // 4. Crear el registro del Usuario en la tabla 'usuarios'
        $usuario = Usuario::create([
            'nombre' => $datosValidados['nombre'],
            'apellido' => $datosValidados['apellido'] ?? null,
            'email' => $datosValidados['email'],
            'telefono' => $datosValidados['telefono'] ?? null,
            'password' => Hash::make($datosValidados['password']),
            'rol_id' => $rolId,
            'esta_aprobado' => $estaAprobado,
        ]);

        $correoEnviado = false;
        $errorCorreo = null;

        // 5. Estructurar y guardar los perfiles adicionales específicos del rol
        if ($nombreRol === 'proveedor') {
            $guardarDoc = function (string $campo) use ($request) {
                if ($request->hasFile($campo)) {
                    $archivo = $request->file($campo);
                    $nombre = time() . '_' . $campo . '_' . preg_replace('/\s+/', '_', $archivo->getClientOriginalName());
                    $ruta = $archivo->storeAs('documentos', $nombre, 'public');
                    return '/storage/' . $ruta;
                }
                return null;
            };

            PerfilProveedor::create([
                'usuario_id' => $usuario->id,
                'categoria_servicio' => $datosValidados['categoria_servicio'],
                'biografia' => $request->input('biografia'),
                'anios_experiencia' => $request->input('anios_experiencia', 0),
                'precio_por_hora' => $request->input('precio_por_hora', 0.00),
                'habilidades' => $request->input('habilidades'),
                'horario_atencion' => $request->input('horario_atencion'),
                'doc_cedula'  => $guardarDoc('doc_cedula'),
                'doc_rut'     => $guardarDoc('doc_rut'),
                'doc_diploma' => $guardarDoc('doc_diploma'),
                'esta_verificado' => false,
            ]);

            // Despachar la notificación por correo informando sobre el registro y la verificación pendiente
            try {
                Mail::to($usuario->email)->send(new ProveedorRegistradoMail($usuario));
                $correoEnviado = true;
            } catch (\Exception $e) {
                // Se registra el fallo del SMTP en los logs del servidor para no detener la petición
                Log::error('Fallo en el envío del correo SMTP para nuevo proveedor: ' . $e->getMessage());
                $errorCorreo = $e->getMessage();
            }
        } elseif ($nombreRol === 'cliente') {
            // Crear el Perfil de Cliente vinculado al Usuario (sin dirección obligatoria inicial)
            PerfilCliente::create([
                'usuario_id' => $usuario->id,
                'direccion' => $request->input('direccion'),
                'ciudad' => $request->input('ciudad'),
            ]);
        }

        // 6. Emitir el token JWT primario para la sesión recién creada
        $token = JWTAuth::fromUser($usuario);

        return response()->json([
            'mensaje' => 'Usuario registrado con éxito en LocalService.',
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'email' => $usuario->email,
                'rol' => $nombreRol,
                'esta_aprobado' => $usuario->esta_aprobado,
            ],
            'perfil_proveedor_creado' => $nombreRol === 'proveedor',
            'perfil_cliente_creado' => $nombreRol === 'cliente',
            'notificacion_correo' => [
                'enviado' => $correoEnviado,
                'error' => $errorCorreo,
            ],
            'token_acceso' => $token,
            'tipo_token' => 'bearer',
        ], 201);
    }

    /**
     * Inicia sesión del usuario autenticando credenciales.
     * Implementa un bloqueo estricto si el proveedor no ha sido aprobado por el administrador.
     */
    public function iniciarSesion(Request $request)
    {
        // 1. Validar la estructura de la solicitud
        $credenciales = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // 2. Intentar autenticar contra la base de datos de usuarios
        if (!$token = JWTAuth::attempt([
            'email' => $credenciales['email'],
            'password' => $credenciales['password']
        ])) {
            return response()->json(['mensaje' => 'Credenciales inválidas.'], 401);
        }

        $usuario = JWTAuth::user();

        // 3. CONTROL DE ACCESO CRÍTICO: Bloquear proveedores con estatus pendiente
        if (!$usuario->esta_aprobado) {
            try {
                // Revocar el JWT generado inmediatamente para cerrar cualquier canal de acceso
                JWTAuth::invalidate($token);
            } catch (\Exception $e) {
                // Ignorar si no se puede revocar en el driver
            }
            
            return response()->json([
                'mensaje' => 'Su cuenta aún no ha sido aprobada por el administrador. Por favor, espere la verificación de sus documentos.'
            ], 403);
        }

        // 4. Cargar relación de rol para anexar detalles informativos en la respuesta
        $usuario->load('rol');

        return response()->json([
            'mensaje' => 'Inicio de sesión exitoso.',
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'email' => $usuario->email,
                'rol' => $usuario->rol ? $usuario->rol->nombre : null,
                'esta_aprobado' => $usuario->esta_aprobado,
            ],
            'token_acceso' => $token,
            'tipo_token' => 'bearer',
        ]);
    }

    /**
     * Administrador: Devuelve el listado de proveedores pendientes de verificación de identidad.
     */
    public function obtenerProveedoresPendientes()
    {
        // 1. Verificar roles del emisor
        $administrador = JWTAuth::user();
        if (!$administrador || $administrador->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado. Se requieren privilegios de administrador.'], 403);
        }

        // 2. Filtrar proveedores pendientes de aprobación junto a sus perfiles públicos
        $proveedoresPendientes = Usuario::where('rol_id', 2)
            ->where('esta_aprobado', false)
            ->with('perfilProveedor')
            ->get();

        return response()->json([
            'proveedores_pendientes' => $proveedoresPendientes
        ]);
    }

    /**
     * Administrador: Valida y aprueba la cuenta y documentación de un proveedor.
     * Actualiza estados, activa la insignia del perfil público y envía correo SMTP de confirmación.
     */
    public function aprobarProveedor(Request $request, $id)
    {
        // 1. Verificar privilegios del emisor
        $administrador = JWTAuth::user();
        if (!$administrador || $administrador->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado. Se requieren privilegios de administrador.'], 403);
        }

        // 2. Buscar al proveedor con su perfil
        $usuario = Usuario::with('perfilProveedor')->find($id);

        if (!$usuario) {
            return response()->json(['mensaje' => 'Usuario no encontrado.'], 404);
        }

        // 3. Confirmar que el usuario objetivo sea de rol proveedor
        if ($usuario->rol_id !== 2) {
            return response()->json([
                'mensaje' => 'El usuario especificado no pertenece a la categoría de proveedores.'
            ], 400);
        }

        // 4. Validar si ya está activo
        if ($usuario->esta_aprobado) {
            return response()->json([
                'mensaje' => 'Este proveedor ya se encuentra aprobado y activo.'
            ], 400);
        }

        // 5. Realizar el cambio de estatus de aprobación
        $usuario->esta_aprobado = true;
        $usuario->save();

        // 6. Activar la marca de verificación en su perfil público de proveedor
        if ($usuario->perfilProveedor) {
            $usuario->perfilProveedor->esta_verificado = true;
            $usuario->perfilProveedor->save();
        }

        $correoEnviado = false;
        $errorCorreo = null;

        // 7. Despachar correo electrónico de confirmación con Gmail SMTP
        try {
            Mail::to($usuario->email)->send(new ProveedorAprobadoMail($usuario));
            $correoEnviado = true;
        } catch (\Exception $e) {
            // Registrar error de SMTP para mantenimiento técnico
            Log::error('Fallo al despachar correo de proveedor aprobado: ' . $e->getMessage());
            $errorCorreo = $e->getMessage();
        }

        return response()->json([
            'mensaje' => 'Proveedor aprobado con éxito y cuenta activada de forma correcta.',
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'email' => $usuario->email,
                'esta_aprobado' => $usuario->esta_aprobado,
            ],
            'notificacion_correo' => [
                'enviado' => $correoEnviado,
                'error' => $errorCorreo,
            ]
        ], 200);
    }

    /**
     * Refresca la sesión activa emitiendo un nuevo token JWT de renovación.
     */
    public function refrescarToken()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            return response()->json([
                'token_acceso' => $token,
                'tipo_token' => 'bearer',
            ]);
        } catch (JWTException $e) {
            return response()->json(['mensaje' => 'No se pudo refrescar el token de acceso.'], 401);
        }
    }

    /**
     * Invalida de forma permanente la sesión activa del emisor.
     */
    public function cerrarSesion()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['mensaje' => 'Sesión cerrada exitosamente en el servidor.']);
        } catch (JWTException $e) {
            return response()->json(['mensaje' => 'Error al intentar cerrar la sesión.'], 500);
        }
    }

    /**
     * Devuelve el objeto del usuario autenticado con todo su rol y perfil cargado en español.
     */
    public function obtenerUsuarioAutenticado()
    {
        $usuario = JWTAuth::user();
        if (!$usuario) {
            return response()->json(['mensaje' => 'No autorizado'], 401);
        }

        // Cargar las relaciones relacionales de perfiles en español
        $usuario->load(['rol', 'perfilCliente', 'perfilProveedor']);
        return response()->json($usuario);
    }

    /**
     * Administrador: Obtiene la lista completa de todos los usuarios registrados
     * con sus perfiles de cliente y proveedor correspondientes.
     */
    public function obtenerUsuarios()
    {
        $administrador = JWTAuth::user();
        if (!$administrador || $administrador->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado. Se requieren privilegios de administrador.'], 403);
        }

        $usuarios = Usuario::with(['rol', 'perfilCliente', 'perfilProveedor'])->get();

        return response()->json([
            'usuarios' => $usuarios
        ]);
    }

    /**
     * Administrador: Elimina a un usuario de la base de datos de manera definitiva.
     */
    public function eliminarUsuario($id)
    {
        $administrador = JWTAuth::user();
        if (!$administrador || $administrador->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado. Se requieren privilegios de administrador.'], 403);
        }

        $usuario = Usuario::find($id);
        if (!$usuario) {
            return response()->json(['mensaje' => 'Usuario no encontrado.'], 404);
        }

        // Evitar que el administrador se elimine a sí mismo
        if ($usuario->id === $administrador->id) {
            return response()->json(['mensaje' => 'No puedes eliminar tu propia cuenta de administrador.'], 400);
        }

        $usuario->delete();

        return response()->json([
            'mensaje' => 'Usuario eliminado con éxito de LocalService.'
        ]);
    }

    /**
     * Administrador: Rechaza la verificación de un proveedor, guarda nota y envía correo.
     */
    public function rechazarProveedor(Request $request, $id)
    {
        $administrador = JWTAuth::user();
        if (!$administrador || $administrador->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado. Se requieren privilegios de administrador.'], 403);
        }

        $usuario = Usuario::with('perfilProveedor')->find($id);
        if (!$usuario) {
            return response()->json(['mensaje' => 'Usuario no encontrado.'], 404);
        }

        $nota = $request->input('nota', null);

        // Guardar nota en el perfil antes de eliminar
        if ($usuario->perfilProveedor && $nota) {
            $usuario->perfilProveedor->update(['nota_rechazo' => $nota]);
        }

        $correoEnviado = false;
        $errorCorreo = null;

        try {
            Mail::to($usuario->email)->send(new ProveedorRechazadoMail($usuario, $nota));
            $correoEnviado = true;
        } catch (\Exception $e) {
            Log::error('Fallo al enviar correo de rechazo: ' . $e->getMessage());
            $errorCorreo = $e->getMessage();
        }

        $usuario->delete();

        return response()->json([
            'mensaje' => 'Solicitud rechazada. Se notificó al proveedor por correo.',
            'notificacion_correo' => ['enviado' => $correoEnviado, 'error' => $errorCorreo],
        ]);
    }

    /**
     * Administrador: Ver documento de un proveedor pendiente.
     */
    public function verDocumentoProveedor($id)
    {
        $administrador = JWTAuth::user();
        if (!$administrador || $administrador->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado.'], 403);
        }

        $usuario = Usuario::with('perfilProveedor')->find($id);
        if (!$usuario || !$usuario->perfilProveedor) {
            return response()->json(['mensaje' => 'Proveedor no encontrado.'], 404);
        }

        $p = $usuario->perfilProveedor;
        return response()->json([
            'nombre' => $usuario->nombre . ' ' . $usuario->apellido,
            'email' => $usuario->email,
            'categoria' => $p->categoria_servicio,
            'doc_cedula'  => $p->doc_cedula,
            'doc_rut'     => $p->doc_rut,
            'doc_diploma' => $p->doc_diploma,
        ]);
    }

    /**
     * Administrador: Compila estadísticas reales sobre usuarios, roles, verificaciones y servicios.
     */
    public function obtenerEstadisticas()
    {
        $administrador = JWTAuth::user();
        if (!$administrador || $administrador->rol_id !== 3) {
            return response()->json(['mensaje' => 'Acceso denegado. Se requieren privilegios de administrador.'], 403);
        }

        $totalUsuarios = Usuario::count();
        $proveedoresVerificados = Usuario::where('rol_id', 2)->where('esta_aprobado', true)->count();
        $verificacionesPendientes = Usuario::where('rol_id', 2)->where('esta_aprobado', false)->count();
        
        // Contadores por rol
        $totalClientes = Usuario::where('rol_id', 1)->count();
        $totalProveedores = Usuario::where('rol_id', 2)->count();

        // Contadores por categoría de servicio populares
        $categorias = PerfilProveedor::selectRaw('categoria_servicio, count(*) as total')
            ->groupBy('categoria_servicio')
            ->orderBy('total', 'desc')
            ->get();

        return response()->json([
            'total_usuarios' => $totalUsuarios,
            'proveedores_verificados' => $proveedoresVerificados,
            'verificaciones_pendientes' => $verificacionesPendientes,
            'resenas_reportadas' => 0, // Mock para cumplir con la UI existente
            'clientes_count' => $totalClientes,
            'proveedores_count' => $totalProveedores,
            'categorias' => $categorias
        ]);
    }

    /**
     * Solicita la recuperación de contraseña enviando un enlace con un token seguro.
     */
    public function solicitarRecuperacionPassword(Request $request)
    {
        // 1. Validar que el correo exista en el sistema
        $datosValidados = $request->validate([
            'email' => 'required|email|exists:usuarios,email',
        ]);

        $email = $datosValidados['email'];
        $usuario = Usuario::where('email', $email)->first();

        // 2. Generar un token seguro y único
        $token = Str::random(60);

        // 3. Registrar o actualizar el token en la tabla 'password_reset_tokens'
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token), // Almacenamos el hash por seguridad
                'created_at' => Carbon::now()
            ]
        );

        $correoEnviado = false;
        $errorCorreo = null;

        // 4. Enviar el correo de restablecimiento en español
        try {
            Mail::to($email)->send(new RestablecerPasswordMail($usuario, $token, $email));
            $correoEnviado = true;
        } catch (\Exception $e) {
            Log::error('Fallo al enviar correo de recuperación SMTP: ' . $e->getMessage());
            $errorCorreo = $e->getMessage();
        }

        return response()->json([
            'mensaje' => 'Se ha enviado un enlace de recuperación a su correo electrónico.',
            'notificacion_correo' => [
                'enviado' => $correoEnviado,
                'error' => $errorCorreo
            ]
        ]);
    }

    /**
     * Restablece la contraseña del usuario tras validar el token enviado.
     */
    public function restablecerPassword(Request $request)
    {
        // 1. Validar datos requeridos
        $datosValidados = $request->validate([
            'email' => 'required|email|exists:usuarios,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = $datosValidados['email'];
        $token = $datosValidados['token'];

        // 2. Buscar el registro del token
        $registroToken = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$registroToken) {
            return response()->json(['mensaje' => 'No se ha solicitado recuperación para esta cuenta o el token es inválido.'], 400);
        }

        // 3. Validar si el token ha expirado (30 minutos de validez)
        $fechaCreacion = Carbon::parse($registroToken->created_at);
        if ($fechaCreacion->addMinutes(30)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json(['mensaje' => 'El enlace de recuperación ha expirado. Por favor, solicite uno nuevo.'], 400);
        }

        // 4. Validar el token cifrado
        if (!Hash::check($token, $registroToken->token)) {
            return response()->json(['mensaje' => 'Token de seguridad inválido o corrompido.'], 400);
        }

        // 5. Restablecer la contraseña en la cuenta del usuario
        $usuario = Usuario::where('email', $email)->first();
        $usuario->password = Hash::make($datosValidados['password']);
        $usuario->save();

        // 6. Eliminar el token usado para evitar dobles solicitudes
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'mensaje' => 'Su contraseña ha sido restablecida con éxito. Ya puede iniciar sesión.'
        ]);
    }
}
