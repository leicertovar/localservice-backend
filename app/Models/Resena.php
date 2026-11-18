<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resena extends Model
{
    protected $table = 'resenas';

    protected $fillable = [
        'cliente_id',
        'proveedor_id',
        'solicitud_id',
        'calificacion',
        'comentario',
        'respuesta_proveedor',
        'esta_reportada',
        'motivo_reporte',
        'reportado_por',
        'esta_ocultada',
        'es_atipica',
        'revision_atipica',
        'revisada_por',
        'revisada_en',
    ];

    protected $casts = [
        'esta_reportada' => 'boolean',
        'esta_ocultada' => 'boolean',
        'es_atipica' => 'boolean',
        'revisada_en' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cliente_id', 'id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'proveedor_id', 'id');
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudServicio::class, 'solicitud_id', 'id');
    }

    /** Todas las fotos (también las ocultadas por el administrador). */
    public function imagenes(): HasMany
    {
        return $this->hasMany(ResenaImagen::class, 'resena_id')->orderBy('orden');
    }

    /** Fotos visibles para el público. */
    public function imagenesVisibles(): HasMany
    {
        return $this->imagenes()->where('esta_ocultada', false);
    }
}
