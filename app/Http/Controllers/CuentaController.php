<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Models\Notificacion;
use App\Models\PerfilCliente;
use App\Models\SolicitudServicio;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Gestión de la cuenta por el propio usuario (app móvil):
 * cambiar contraseña, desactivar la cuenta y eliminarla.
 */
class CuentaController extends Controller
{
    /** Texto que el usuario debe escribir para confirmar la eliminación. */
    private const CONFIRMACION_ELIMINAR = 'ELIMINAR';

    /** Estados de solicitud que se cancelan si el cliente elimina su cuenta. */
    private const ESTADOS_ACTIVOS = ['pendiente', 'cotizado', 'aceptada'];

    /**
     * Cambia la contraseña del usuario autenticado (requiere la actual).
     */
    public function cambiarPassword(Request $request)
    {
        $usuario = JWTAuth::user();

        $datos = $request->validate([
            'password_actual' => 'required|string',
            'password' => 'required|string|min:8|confirmed|different:password_actual',
        ], [
            'password_actual.required' => 'Escribe tu contraseña actual.',
            'password.required' => 'Escribe la nueva contraseña.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ]);

        if (!Hash::check($datos['password_actual'], $usuario->password)) {
            return response()->json([
                'mensaje' => 'La contraseña actual no es correcta.',
                'errors' => ['password_actual' => ['La contraseña actual no es correcta.']],
            ], 422);
        }

        $usuario->password = Hash::make($datos['password']);
        $usuario->save();

        // Un enlace o código de recuperación pendiente ya no debe servir
        DB::table('password_reset_tokens')->where('email', $usuario->email)->delete();
        DB::table('codigos_recuperacion')->where('email', $usuario->email)->delete();

        return response()->json(['mensaje' => 'Tu contraseña se actualizó correctamente.']);
    }

    /**
     * Desactiva la cuenta del cliente. Se reactiva al volver a iniciar sesión.
     */
    public function desactivarCuenta(Request $request)
    {
        $usuario = JWTAuth::user();
        if ($respuesta = $this->validarClienteYPassword($request, $usuario)) {
            return $respuesta;
        }

        $usuario->desactivado_en = Carbon::now();
        $usuario->save();
        $this->invalidarTokenActual();

        return response()->json([
            'mensaje' => 'Tu cuenta quedó desactivada. Puedes reactivarla cuando quieras iniciando sesión.',
        ]);
    }

    /**
     * Elimina la cuenta del cliente de forma definitiva.
     * Los datos personales se anonimizan; las solicitudes, reseñas y mensajes se conservan
     * como "Usuario eliminado" para no borrar el historial de los proveedores.
     */
    public function eliminarCuenta(Request $request)
    {
        $usuario = JWTAuth::user();
        if ($respuesta = $this->validarClienteYPassword($request, $usuario, true)) {
            return $respuesta;
        }

        $emailOriginal = $usuario->email;
        $fotoAnterior = PerfilCliente::where('usuario_id', $usuario->id)->value('foto_perfil');

        DB::transaction(function () use ($usuario, $emailOriginal) {
            // 1. Cancelar las solicitudes en curso y avisar a sus proveedores
            $activas = SolicitudServicio::where('cliente_id', $usuario->id)
                ->whereIn('estado', self::ESTADOS_ACTIVOS)
                ->get();

            foreach ($activas as $solicitud) {
                $solicitud->estado = 'rechazada';
                $solicitud->save();

                Notificacion::crear(
                    $solicitud->proveedor_id,
                    'solicitud',
                    'Solicitud cancelada',
                    "El cliente eliminó su cuenta y la solicitud #{$solicitud->id} quedó cancelada."
                );
            }

            // 2. Quitar los datos de contacto de todas sus solicitudes
            SolicitudServicio::where('cliente_id', $usuario->id)->update([
                'telefono' => '',
                'direccion' => 'Dirección eliminada',
                'latitud' => null,
                'longitud' => null,
            ]);

            // 3. Borrar sus datos personales
            PerfilCliente::where('usuario_id', $usuario->id)->update([
                'direccion' => null,
                'ciudad' => null,
                'foto_perfil' => null,
                'preferencias' => null,
            ]);
            Notificacion::where('usuario_id', $usuario->id)->delete();
            DB::table('password_reset_tokens')->where('email', $emailOriginal)->delete();
            DB::table('codigos_recuperacion')->where('email', $emailOriginal)->delete();

            // 4. Anonimizar la cuenta (el correo queda libre para registrarse de nuevo)
            $usuario->nombre = 'Usuario';
            $usuario->apellido = 'eliminado';
            $usuario->email = "eliminado+{$usuario->id}@localservice.invalid";
            $usuario->telefono = null;
            $usuario->password = Hash::make(Str::random(40));
            $usuario->eliminado_en = Carbon::now();
            $usuario->save();
        });

        // 5. Fuera de la transacción: borrar la foto del disco e invalidar la sesión
        if ($fotoAnterior) {
            Storage::disk('public')->delete(Str::after($fotoAnterior, '/storage/'));
        }
        $this->invalidarTokenActual();

        return response()->json(['mensaje' => 'Tu cuenta fue eliminada.']);
    }

    /**
     * Solo clientes, con su contraseña actual (y, al eliminar, la palabra de confirmación).
     * Devuelve una respuesta de error o null si todo es correcto.
     */
    private function validarClienteYPassword(Request $request, $usuario, bool $eliminar = false)
    {
        if (!$usuario || $usuario->rol_id !== 1) {
            return response()->json([
                'mensaje' => 'Por ahora solo los clientes pueden gestionar su cuenta desde la app.',
            ], 403);
        }

        $reglas = ['password' => 'required|string'];
        $mensajes = ['password.required' => 'Escribe tu contraseña para confirmar.'];
        if ($eliminar) {
            $reglas['confirmacion'] = 'required|in:' . self::CONFIRMACION_ELIMINAR;
            $mensajes['confirmacion.required'] = 'Escribe ' . self::CONFIRMACION_ELIMINAR . ' para confirmar.';
            $mensajes['confirmacion.in'] = 'Escribe ' . self::CONFIRMACION_ELIMINAR . ' en mayúsculas para confirmar.';
        }
        $request->validate($reglas, $mensajes);

        if (!Hash::check($request->input('password'), $usuario->password)) {
            return response()->json([
                'mensaje' => 'La contraseña no es correcta.',
                'errors' => ['password' => ['La contraseña no es correcta.']],
            ], 422);
        }

        return null;
    }

    private function invalidarTokenActual(): void
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Exception $e) {
            Log::warning('No se pudo invalidar el token al cerrar la cuenta: ' . $e->getMessage());
        }
    }
}
