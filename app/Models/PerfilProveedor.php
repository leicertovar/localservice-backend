<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerfilProveedor extends Model
{
    protected $table = 'perfiles_proveedor';

    /**
     * Atributos asignables masivamente.
     */
    protected $fillable = [
        'usuario_id',
        'categoria_servicio',
        'biografia',
        'anios_experiencia',
        'precio_por_hora',
        'habilidades',
        'enlaces_portafolio',
        'horario_atencion',
        'calificacion',
        'total_resenas',
        'url_documento',
        'esta_verificado',
    ];

    /**
     * Conversión de tipos de atributos.
     */
    protected function casts(): array
    {
        return [
            'esta_verificado' => 'boolean',
            'calificacion' => 'decimal:2',
            'precio_por_hora' => 'decimal:2',
            'anios_experiencia' => 'integer',
            'total_resenas' => 'integer',
        ];
    }

    /**
     * Obtener el usuario propietario de este perfil de proveedor.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }
}
