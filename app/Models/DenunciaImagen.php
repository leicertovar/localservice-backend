<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Denuncia de una foto de reseña o de servicio, para revisión del administrador. */
class DenunciaImagen extends Model
{
    protected $table = 'denuncias_imagen';

    protected $fillable = ['tipo', 'imagen_id', 'denunciante_id', 'motivo', 'estado', 'decision', 'revisada_por', 'revisada_en'];

    protected $casts = ['revisada_en' => 'datetime'];

    public function denunciante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'denunciante_id');
    }
}
