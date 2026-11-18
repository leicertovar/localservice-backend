<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'zona',
        'tipo_cobro',
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

    /** Todas las fotos (el proveedor ve también las ocultadas por el administrador). */
    public function imagenes(): HasMany
    {
        return $this->hasMany(ServicioImagen::class, 'servicio_id')->orderBy('orden');
    }

    /** Fotos visibles para los clientes. */
    public function imagenesVisibles(): HasMany
    {
        return $this->imagenes()->where('esta_ocultada', false);
    }
}
