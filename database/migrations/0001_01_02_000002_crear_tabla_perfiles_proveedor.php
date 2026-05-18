<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones de la tabla de perfiles de proveedores.
     */
    public function up(): void
    {
        Schema::create('perfiles_proveedor', function (Blueprint $table) {
            $table->id();
            $table->uuid('usuario_id');
            $table->string('categoria_servicio');
            $table->text('biografia')->nullable();
            $table->integer('anios_experiencia')->default(0);
            $table->decimal('precio_por_hora', 10, 2)->default(0.00);
            $table->text('habilidades')->nullable(); // Guardado como lista o texto
            $table->text('enlaces_portafolio')->nullable(); // Sitios web o portafolios
            $table->string('horario_atencion')->nullable(); // Ejemplo: Lunes a Viernes 8 AM - 6 PM
            $table->decimal('calificacion', 3, 2)->default(0.00);
            $table->integer('total_resenas')->default(0);
            $table->string('url_documento')->nullable(); // Enlace al PDF o imagen cargada
            $table->boolean('esta_verificado')->default(false); // Badge de validación
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
        Schema::dropIfExists('perfiles_proveedor');
    }
};
