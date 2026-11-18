<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotos de reseñas y de servicios (máximo 3 por reseña o servicio) y denuncias de esas fotos.
 * Una foto denunciada sigue visible hasta que el administrador decide ocultarla o mantenerla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resena_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resena_id')->constrained('resenas')->cascadeOnDelete();
            $table->string('ruta');
            $table->unsignedTinyInteger('orden')->default(0);
            $table->boolean('esta_ocultada')->default(false);
            $table->timestamps();
        });

        Schema::create('servicio_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->string('ruta');
            $table->unsignedTinyInteger('orden')->default(0);
            $table->boolean('esta_ocultada')->default(false);
            $table->timestamps();
        });

        Schema::create('denuncias_imagen', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // resena | servicio
            $table->unsignedBigInteger('imagen_id');
            $table->uuid('denunciante_id');
            $table->string('motivo', 255);
            $table->string('estado', 20)->default('pendiente'); // pendiente | resuelta
            $table->string('decision', 20)->nullable(); // ocultar | mantener
            $table->uuid('revisada_por')->nullable();
            $table->timestamp('revisada_en')->nullable();
            $table->timestamps();

            // Una persona denuncia una misma foto una sola vez
            $table->unique(['tipo', 'imagen_id', 'denunciante_id']);
            $table->index(['estado', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('denuncias_imagen');
        Schema::dropIfExists('servicio_imagenes');
        Schema::dropIfExists('resena_imagenes');
    }
};
