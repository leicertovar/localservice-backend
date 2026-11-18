<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto de un servicio publicado por el proveedor (máximo 3). */
class ServicioImagen extends Model
{
    protected $table = 'servicio_imagenes';

    protected $fillable = ['servicio_id', 'ruta', 'orden', 'esta_ocultada'];

    protected $casts = ['esta_ocultada' => 'boolean'];

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }
}
