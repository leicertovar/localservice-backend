<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
