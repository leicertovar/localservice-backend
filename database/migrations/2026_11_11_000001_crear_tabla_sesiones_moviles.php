<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sesiones "Recuérdame" de la app móvil (tokens de refresco).
     * - Del token solo se guarda su hash SHA-256; el valor real vive en el almacén seguro del teléfono.
     * - Cada uso lo rota: se revoca el usado y se emite otro de la misma "familia".
     * - expira_en: vence tras 30 días sin abrir la app. caduca_en: límite absoluto (120 días).
     */
    public function up(): void
    {
        Schema::create('sesiones_moviles', function (Blueprint $table) {
            $table->id();
            $table->uuid('usuario_id');
            $table->uuid('familia');
            $table->string('token_hash', 64)->unique();
            $table->string('dispositivo', 120)->nullable();
            $table->timestamp('expira_en');
            $table->timestamp('caduca_en');
            $table->timestamp('ultimo_uso_en')->nullable();
            $table->timestamp('revocado_en')->nullable();
            $table->string('motivo_revocacion', 40)->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->index(['usuario_id', 'revocado_en']);
            $table->index('familia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones_moviles');
    }
};
