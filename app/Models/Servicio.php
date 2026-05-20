<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Servicio extends Model
{
    // Nombre de la tabla asociada en la base de datos
    protected $table = 'servicios';

    // Atributos que se pueden asignar de manera masiva
    protected $fillable = [
        'usuario_id',
        'nombre',
        'categoria',
        'precio',
        'descripcion',
        'esta_activo',
    ];

    // Casts de atributos para formatear tipos de datos
    protected function casts(): array
    {
        return [
            'esta_activo' => 'boolean',
        ];
    }

    /**
     * Obtiene el proveedor (usuario) al que pertenece este servicio.
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }
}
