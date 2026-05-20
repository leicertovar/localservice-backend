<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración para crear la tabla de servicios.
     */
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->uuid('usuario_id');
            $table->string('nombre');
            $table->string('categoria');
            $table->string('precio'); // Guardamos como string para admitir formatos como $50.000/hora o $80.000
            $table->text('descripcion')->nullable();
            $table->boolean('esta_activo')->default(true);
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->index('usuario_id');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};
