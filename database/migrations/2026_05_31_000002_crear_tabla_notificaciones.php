<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->uuid('usuario_id');
            $table->string('tipo'); // solicitud, mensaje, resena, pago, sistema, verificacion
            $table->string('titulo');
            $table->text('descripcion');
            $table->boolean('leida')->default(false);
            $table->string('enlace')->nullable(); // ruta opcional al recurso relacionado
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->index(['usuario_id', 'leida']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
