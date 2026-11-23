<?php

namespace App\Services;

use App\Models\DispositivoPush;
use App\Models\Notificacion;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notificaciones push con Firebase Cloud Messaging (API HTTP v1).
 *
 * Credenciales (cuenta de servicio de Firebase), en este orden:
 *  - FIREBASE_CREDENTIALS_JSON: el JSON completo o en base64 (Railway: una variable, sin archivos);
 *  - FIREBASE_CREDENTIALS: ruta al archivo JSON;
 *  - storage/app/firebase/credenciales-firebase.json (desarrollo; está en .gitignore).
 * Sin credenciales, el push se omite y la app sigue funcionando con las notificaciones internas.
 */
class PushFirebase
{
    private const ALCANCE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** Canal de Android creado por la app (importancia alta: suena y aparece arriba). */
    public const CANAL_ANDROID = 'localservice';

    /** Envía la notificación a todos los teléfonos del usuario. Devuelve cuántos la aceptaron. */
    public static function enviarNotificacion(Notificacion $notificacion): int
    {
        $dispositivos = DispositivoPush::where('usuario_id', $notificacion->usuario_id)->get();
        if ($dispositivos->isEmpty()) {
            return 0;
        }
        $credenciales = self::credenciales();
        if (!$credenciales) {
            return 0;
        }

        $tokenAcceso = self::tokenAcceso($credenciales);
        $url = "https://fcm.googleapis.com/v1/projects/{$credenciales['project_id']}/messages:send";
        $enviados = 0;

        foreach ($dispositivos as $dispositivo) {
            $respuesta = Http::withToken($tokenAcceso)->timeout(10)->post($url, [
                'message' => [
                    'token' => $dispositivo->token,
                    'notification' => [
                        'title' => $notificacion->titulo,
                        'body' => mb_substr($notificacion->descripcion, 0, 240),
                    ],
                    // Al tocarla, la app abre la pantalla del enlace ("solicitud:12", "chat:<id>"…)
                    'data' => [
                        'notificacion_id' => (string) $notificacion->id,
                        'enlace' => (string) ($notificacion->enlace ?? ''),
                        'tipo' => (string) $notificacion->tipo,
                    ],
                    'android' => [
                        'priority' => 'high',
                        'ttl' => '86400s',
                        'notification' => [
                            'channel_id' => self::CANAL_ANDROID,
                            'icon' => 'ic_stat_localservice',
                            'color' => '#1A75EE',
                            'sound' => 'default',
                            // Mensajes de la misma persona se agrupan en una sola notificación
                            'tag' => $notificacion->enlace ?: 'notificacion-' . $notificacion->id,
                        ],
                    ],
                ],
            ]);

            if ($respuesta->successful()) {
                $enviados++;
                continue;
            }
            $codigo = $respuesta->json('error.details.0.errorCode') ?? $respuesta->json('error.status');
            // Token vencido o de una app desinstalada: se borra para no seguir intentando
            if (in_array($codigo, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true) || $respuesta->status() === 404) {
                $dispositivo->delete();
            } else {
                Log::warning('FCM rechazó el push', ['estado' => $respuesta->status(), 'codigo' => $codigo]);
            }
        }

        return $enviados;
    }

    /** Credenciales de la cuenta de servicio, o null si no están configuradas. */
    public static function credenciales(): ?array
    {
        return once(function () {
            $json = config('services.firebase.credentials_json');
            if ($json && !str_starts_with(trim($json), '{')) {
                $json = base64_decode($json, true) ?: null;
            }
            if (!$json) {
                $ruta = config('services.firebase.credentials') ?: storage_path('app/firebase/credenciales-firebase.json');
                $json = is_file($ruta) ? file_get_contents($ruta) : null;
            }
            $datos = $json ? json_decode($json, true) : null;
            if (!is_array($datos) || empty($datos['project_id']) || empty($datos['private_key'])) {
                Log::info('Push desactivado: faltan las credenciales de Firebase.');
                return null;
            }
            return $datos;
        });
    }

    /** Token OAuth para FCM (dura 1 hora; se guarda en caché unos 55 minutos). */
    private static function tokenAcceso(array $credenciales): string
    {
        return Cache::remember('fcm_token_acceso', now()->addMinutes(55), function () use ($credenciales) {
            $token = (new ServiceAccountCredentials(self::ALCANCE, $credenciales))->fetchAuthToken();
            return $token['access_token'];
        });
    }
}
