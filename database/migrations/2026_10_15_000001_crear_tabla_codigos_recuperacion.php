<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Códigos de 6 dígitos para recuperar la contraseña desde la app móvil.
     * Un solo código vigente por correo; se guarda cifrado y cuenta los intentos fallidos.
     */
    public function up(): void
    {
        Schema::create('codigos_recuperacion', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('codigo');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_recuperacion');
    }
};
