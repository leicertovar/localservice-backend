<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones de la tabla de perfiles de clientes.
     */
    public function up(): void
    {
        Schema::create('perfiles_cliente', function (Blueprint $table) {
            $table->id();
            $table->uuid('usuario_id');
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('foto_perfil')->nullable();
            $table->text('preferencias')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->index('usuario_id');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('perfiles_cliente');
    }
};
