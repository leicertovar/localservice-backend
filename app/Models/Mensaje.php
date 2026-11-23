<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mensaje extends Model
{
    protected $table = 'mensajes';

    /** Minutos durante los que el emisor puede editar su mensaje. */
    public const MINUTOS_EDITAR = 5;

    /** Segundos durante los que el emisor puede eliminarlo para todos. */
    public const SEGUNDOS_ELIMINAR_PARA_TODOS = 60;

    protected $fillable = [
        'emisor_id',
        'receptor_id',
        'contenido',
        'tipo',
        'url_archivo',
        'nombre_archivo',
        'leido',
        'editado_en',
        'eliminado_en',
        'oculto_emisor',
        'oculto_receptor',
    ];

    /** Quién ocultó el mensaje "para sí" es privado: el otro no debe saberlo. */
    protected $hidden = ['oculto_emisor', 'oculto_receptor'];

    protected $casts = [
        'leido' => 'boolean',
        'editado_en' => 'datetime',
        'eliminado_en' => 'datetime',
        'oculto_emisor' => 'boolean',
        'oculto_receptor' => 'boolean',
    ];

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'emisor_id', 'id');
    }

    public function receptor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'receptor_id', 'id');
    }

    /** Mensajes que el usuario no eliminó "para mí". */
    public function scopeVisiblesPara(Builder $q, string $usuarioId): Builder
    {
        return $q->where(function ($q) use ($usuarioId) {
            $q->where(fn ($q) => $q->where('emisor_id', $usuarioId)->where('oculto_emisor', false))
              ->orWhere(fn ($q) => $q->where('receptor_id', $usuarioId)->where('oculto_receptor', false));
        });
    }

    /** Eliminado para todos: no se envía el texto ni el archivo, solo el aviso. */
    public function toArray(): array
    {
        $datos = parent::toArray();
        $datos['eliminado'] = $this->eliminado_en !== null;
        if ($datos['eliminado']) {
            $datos['contenido'] = null;
            $datos['url_archivo'] = null;
            $datos['nombre_archivo'] = null;
        }
        return $datos;
    }
}
