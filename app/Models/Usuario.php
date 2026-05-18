<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\Usuario as Authenticatable; // Laravel auth
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

// Heredamos de Authenticatable de Laravel para el sistema de logins y guardias
class Usuario extends \Illuminate\Foundation\Auth\User implements JWTSubject
{
    use HasFactory, Notifiable;
    use HasUuids; // Trait para UUIDs automáticos de clave primaria

    protected $table = 'usuarios'; // Especificamos la tabla en español

    public $incrementing = false; // Desactivar auto-incremento para UUID
    protected $keyType = 'string'; // Clave primaria tipo string (UUID)

    /**
     * Atributos que se pueden asignar de manera masiva.
     */
    protected $fillable = [
        'nombre',
        'apellido',
        'email',
        'telefono',
        'password',
        'rol_id',
        'esta_aprobado',
    ];

    /**
     * Atributos ocultos en la serialización (como respuestas JSON).
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casting de tipos para campos especiales.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'esta_aprobado' => 'boolean',
        ];
    }

    /**
     * Obtener el rol asociado con el usuario.
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id', 'id');
    }

    /**
     * Obtener el perfil de cliente asociado con el usuario.
     */
    public function perfilCliente(): HasOne
    {
        return $this->hasOne(PerfilCliente::class, 'usuario_id', 'id');
    }

    /**
     * Obtener el perfil de proveedor asociado con el usuario.
     */
    public function perfilProveedor(): HasOne
    {
        return $this->hasOne(PerfilProveedor::class, 'usuario_id', 'id');
    }

    /**
     * Helper para verificar de manera rápida si el usuario tiene un rol específico.
     * Ejemplo: $user->tieneRol('admin')
     */
    public function tieneRol(string $nombreRol): bool
    {
        return $this->relationLoaded('rol') 
            ? ($this->rol && $this->rol->nombre === $nombreRol)
            : ($this->rol()->where('nombre', $nombreRol)->exists());
    }

    /**
     * Obtiene el identificador para la firma del JWT (ID del usuario).
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Agrega claims (campos) personalizados al JWT devuelto en el login.
     * Inyectamos el rol y el estado de aprobación para lectura directa en frontend.
     */
    public function getJWTCustomClaims()
    {
        return [
            'rol' => $this->rol ? $this->rol->nombre : $this->rol()->value('nombre'),
            'esta_aprobado' => $this->esta_aprobado,
        ];
    }
}
