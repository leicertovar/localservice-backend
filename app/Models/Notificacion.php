<?php

namespace App\Models;

use App\Events\NotificacionCreada;
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
     * Cada notificación nueva llega al instante a la app (Laravel Reverb). Se emite después de
     * confirmar la transacción y, si Reverb no responde, la notificación igual queda guardada.
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
