<?php

namespace App\Models;

use App\Events\NotificacionCreada;
use App\Services\PushFirebase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = [
        'usuario_id',
        'tipo',
        'titulo',
        'descripcion',
        'leida',
        'enlace',
    ];

    protected $casts = [
        'leida' => 'boolean',
    ];

    /**
     * Cada notificación nueva llega al instante a la app abierta (Laravel Reverb) y al teléfono
     * como push (Firebase). Se emite después de confirmar la transacción y, si alguno de los dos
     * falla, la notificación igual queda guardada.
     */
    protected static function booted(): void
    {
        static::created(function (self $notificacion) {
            DB::afterCommit(function () use ($notificacion) {
                try {
                    broadcast(new NotificacionCreada($notificacion));
                } catch (\Throwable $e) {
                    Log::warning('No se pudo emitir la notificación en tiempo real: ' . $e->getMessage());
                }
                // Push al teléfono (Firebase), después de enviar la respuesta para no demorarla
                \Illuminate\Support\defer(function () use ($notificacion) {
                    try {
                        PushFirebase::enviarNotificacion($notificacion);
                    } catch (\Throwable $e) {
                        Log::warning('No se pudo enviar el push: ' . $e->getMessage());
                    }
                });
            });
        });
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }

    /**
     * Crea una notificación para un usuario de forma estática.
     */
    public static function crear(string $usuarioId, string $tipo, string $titulo, string $descripcion, string $enlace = null): self
    {
        return self::create([
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'enlace' => $enlace,
        ]);
    }
}
