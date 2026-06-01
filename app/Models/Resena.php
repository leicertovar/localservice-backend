<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'esta_reportada' => 'boolean',
        'esta_ocultada' => 'boolean',
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
}
