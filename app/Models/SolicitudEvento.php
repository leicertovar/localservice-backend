<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un cambio de estado de una solicitud (para la línea de tiempo).
 */
class SolicitudEvento extends Model
{
    protected $table = 'solicitud_eventos';

    public const UPDATED_AT = null;

    protected $fillable = ['solicitud_id', 'estado', 'actor_id', 'nota'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
