<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerfilCliente extends Model
{
    protected $table = 'perfiles_cliente';

    protected $fillable = [
        'usuario_id',
        'direccion',
        'ciudad',
        'foto_perfil',
        'preferencias',
    ];

    /**
     * Obtener el usuario propietario de este perfil de cliente.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }
}
