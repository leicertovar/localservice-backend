<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sesión "Recuérdame" de la app móvil (token de refresco rotativo).
 * Ver SesionMovilController para el ciclo de vida completo.
 */
class SesionMovil extends Model
{
    protected $table = 'sesiones_moviles';

    protected $fillable = [
        'usuario_id',
        'familia',
        'token_hash',
        'dispositivo',
        'expira_en',
        'caduca_en',
        'ultimo_uso_en',
        'revocado_en',
        'motivo_revocacion',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'caduca_en' => 'datetime',
            'ultimo_uso_en' => 'datetime',
            'revocado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** Hash con el que se guarda y busca un token (nunca se guarda el token en claro). */
    public static function hashDe(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Revoca todas las sesiones móviles activas de un usuario
     * (cambio o recuperación de contraseña, desactivar o eliminar la cuenta).
     */
    public static function revocarDeUsuario(string $usuarioId, string $motivo): void
    {
        static::where('usuario_id', $usuarioId)
            ->whereNull('revocado_en')
            ->update(['revocado_en' => Carbon::now(), 'motivo_revocacion' => $motivo]);
    }
}
