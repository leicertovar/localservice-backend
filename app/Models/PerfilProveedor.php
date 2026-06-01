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
        'foto_perfil',
        'nota_rechazo',
        'ciudad',
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
     * Accesor para horario_atencion.
     * Si el valor es un JSON válido (array/objeto), lo decodifica.
     * De lo contrario, retorna el string original de forma segura sin romper la serialización JSON.
     */
    public function getHorarioAtencionAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed !== '' && (($trimmed[0] === '{' && substr($trimmed, -1) === '}') || ($trimmed[0] === '[' && substr($trimmed, -1) === ']'))) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            // Limpiar el estado de error de JSON en PHP decodificando un JSON válido
            json_decode('{}');
        }

        return $value;
    }

    /**
     * Mutador para horario_atencion.
     * Si se le pasa un array/objeto, se codifica a JSON.
     * Si es un string, se almacena directamente.
     */
    public function setHorarioAtencionAttribute($value)
    {
        if (is_array($value) || is_object($value)) {
            $this->attributes['horario_atencion'] = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $this->attributes['horario_atencion'] = $value;
        }
    }

    /**
     * Obtener el usuario propietario de este perfil de proveedor.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }
}
