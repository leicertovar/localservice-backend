<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mensaje extends Model
{
    protected $table = 'mensajes';

    protected $fillable = [
        'emisor_id',
        'receptor_id',
        'contenido',
        'tipo',
        'url_archivo',
        'nombre_archivo',
        'leido',
    ];

    protected $casts = [
        'leido' => 'boolean',
    ];

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'emisor_id', 'id');
    }

    public function receptor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'receptor_id', 'id');
    }
}
