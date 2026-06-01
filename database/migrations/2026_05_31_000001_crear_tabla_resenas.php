<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resenas', function (Blueprint $table) {
            $table->id();
            $table->uuid('cliente_id');
            $table->uuid('proveedor_id');
            $table->unsignedBigInteger('solicitud_id')->nullable();
            $table->tinyInteger('calificacion'); // 1-5
            $table->text('comentario');
            $table->text('respuesta_proveedor')->nullable();
            $table->boolean('esta_reportada')->default(false);
            $table->string('motivo_reporte')->nullable();
            $table->uuid('reportado_por')->nullable();
            $table->boolean('esta_ocultada')->default(false); // admin la oculta
            $table->timestamps();

            $table->foreign('cliente_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->foreign('proveedor_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->foreign('solicitud_id')->references('id')->on('solicitudes_servicio')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resenas');
    }
};
