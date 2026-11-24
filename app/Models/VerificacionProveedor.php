<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verificación de identidad del proveedor: lectura de la cédula, coincidencia con el registro y,
 * más adelante, la puntuación del agente de IA que decide si se aprueba sola o va al administrador.
 */
class VerificacionProveedor extends Model
{
    protected $table = 'verificaciones_proveedor';

    /** Estados del flujo. */
    public const PENDIENTE_IA = 'pendiente_ia';
    public const REVISION_ADMIN = 'revision_admin';
    public const APROBADA = 'aprobada';
    public const RECHAZADA = 'rechazada';

    /** La huella no sale de la API. */
    protected $hidden = ['numero_documento_hash'];

    protected $fillable = [
        'usuario_id',
        'estado',
        'numero_documento',
        'numero_documento_hash',
        'nombre_registro',
        'metodo_extraccion',
        'datos_extraidos',
        'porcentaje_coincidencia',
        'detalle_coincidencia',
        'puntuacion_ia',
        'analisis_ia',
        'modelo_ia',
        'evaluado_ia_en',
        'decision',
        'decidido_por',
        'decidido_en',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            // Datos personales (número, nacimiento, RH…): cifrados en la base de datos
            'numero_documento' => 'encrypted',
            'datos_extraidos' => 'encrypted:array',
            'detalle_coincidencia' => 'array',
            'analisis_ia' => 'array',
            'porcentaje_coincidencia' => 'float',
            'puntuacion_ia' => 'float',
            'evaluado_ia_en' => 'datetime',
            'decidido_en' => 'datetime',
        ];
    }

    /** Huella del número de cédula (HMAC con la APP_KEY) para buscar repetidos. */
    public static function huellaNumero(?string $numero): ?string
    {
        $limpio = preg_replace('/\D/', '', (string) $numero);
        return $limpio ? hash_hmac('sha256', $limpio, (string) config('app.key')) : null;
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }

    /** El administrador (o en el futuro la IA) toma la decisión final. */
    public function decidir(string $estado, string $decision, ?string $decididoPor = null, ?string $nota = null): void
    {
        $this->update([
            'estado' => $estado,
            'decision' => $decision,
            'decidido_por' => $decididoPor,
            'decidido_en' => now(),
            'nota' => $nota,
        ]);
    }
}
