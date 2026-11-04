<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado de la cuenta gestionado por el propio usuario (app móvil):
     * - desactivado_en: cuenta pausada; se reactiva al volver a iniciar sesión.
     * - eliminado_en: cuenta eliminada y anonimizada; no puede volver a usarse.
     */
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->timestamp('desactivado_en')->nullable();
            $table->timestamp('eliminado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['desactivado_en', 'eliminado_en']);
        });
    }
};
