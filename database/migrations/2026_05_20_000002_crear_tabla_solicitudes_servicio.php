<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración para crear la tabla de solicitudes de servicio.
     */
    public function up(): void
    {
        Schema::create('solicitudes_servicio', function (Blueprint $table) {
            $table->id();
            $table->uuid('cliente_id');
            $table->uuid('proveedor_id');
            $table->unsignedBigInteger('servicio_id')->nullable();
            $table->date('fecha');
            $table->string('hora');
            $table->string('direccion');
            $table->text('descripcion');
            $table->string('telefono');
            
            // Campos llenados cuando el proveedor cotiza
            $table->string('monto_cotizado')->nullable();
            $table->string('tiempo_estimado')->nullable();
            $table->string('garantia')->nullable();
            $table->text('detalles_cotizacion')->nullable();
            
            // Estados posibles: 'pendiente', 'cotizado', 'aceptada', 'rechazada', 'completada'
            $table->string('estado')->default('pendiente');
            $table->timestamps();

            $table->foreign('cliente_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->foreign('proveedor_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->foreign('servicio_id')->references('id')->on('servicios')->onDelete('set null');
            
            $table->index('cliente_id');
            $table->index('proveedor_id');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_servicio');
    }
};
