<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editar y eliminar mensajes del chat:
 * - editado_en: el emisor lo editó (hasta 5 minutos después de enviarlo).
 * - eliminado_en: el emisor lo eliminó para todos (hasta 1 minuto después de enviarlo).
 * - oculto_emisor / oculto_receptor: "eliminar para mí" (solo desaparece del chat de quien lo eliminó).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {
            $table->timestamp('editado_en')->nullable();
            $table->timestamp('eliminado_en')->nullable();
            $table->boolean('oculto_emisor')->default(false);
            $table->boolean('oculto_receptor')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {
            $table->dropColumn(['editado_en', 'eliminado_en', 'oculto_emisor', 'oculto_receptor']);
        });
    }
};
