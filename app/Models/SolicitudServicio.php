<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudServicio extends Model
{
    // Nombre de la tabla asociada en la base de datos
    protected $table = 'solicitudes_servicio';

    // Atributos asignables de forma masiva
    protected $fillable = [
        'cliente_id',
        'proveedor_id',
        'servicio_id',
        'fecha',
        'hora',
        'direccion',
        'descripcion',
        'telefono',
        'monto_cotizado',
        'tiempo_estimado',
        'garantia',
        'detalles_cotizacion',
        'estado',
        'latitud',
        'longitud',
        'fecha_completado',
        'marcado_pagado',
        'confirmacion_cliente',
        'queja_cliente',
        'evidencia_cliente',
        'evidencia_proveedor',
        'estado_disputa',
        'resolucion_admin',
        'nota_admin',
    ];

    protected $casts = [
        'marcado_pagado' => 'boolean',
    ];

    /**
     * Obtiene el cliente (usuario) que realizó la solicitud.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cliente_id', 'id');
    }

    /**
     * Obtiene el proveedor (usuario) que recibe la solicitud.
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'proveedor_id', 'id');
    }

    /**
     * Obtiene el servicio específico solicitado.
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'id');
    }
}
