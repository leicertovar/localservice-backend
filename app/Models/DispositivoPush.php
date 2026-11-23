<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Teléfono registrado para recibir notificaciones push (token de Firebase Cloud Messaging). */
class DispositivoPush extends Model
{
    protected $table = 'dispositivos_push';

    protected $fillable = ['usuario_id', 'token', 'plataforma'];

    protected $hidden = ['token'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }
}
