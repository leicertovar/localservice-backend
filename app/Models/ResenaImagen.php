<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto adjunta a una reseña (máximo 3). */
class ResenaImagen extends Model
{
    protected $table = 'resena_imagenes';

    protected $fillable = ['resena_id', 'ruta', 'orden', 'esta_ocultada'];

    protected $casts = ['esta_ocultada' => 'boolean'];

    public function resena(): BelongsTo
    {
        return $this->belongsTo(Resena::class, 'resena_id');
    }
}
