<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teléfonos registrados para notificaciones push (Firebase Cloud Messaging).
 * Un usuario puede tener varios; un token pertenece a un solo usuario (el último que inició sesión).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos_push', function (Blueprint $table) {
            $table->id();
            $table->uuid('usuario_id');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('plataforma', 20)->default('android');
            $table->timestamps();
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_push');
    }
};
