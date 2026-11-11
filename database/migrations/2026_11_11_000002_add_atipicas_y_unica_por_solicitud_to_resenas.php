<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - Valores atípicos (ver App\Services\CalificacionProveedor): la reseña se conserva y se ve,
     *   pero no cuenta en el promedio del proveedor mientras un administrador no la revise.
     *   revision_atipica: null (sin revisar) | 'conservar' (fallo real: vuelve a contar) | 'excluir'.
     * - Una sola reseña por solicitud de servicio (antes solo se validaba en el código).
     */
    public function up(): void
    {
        Schema::table('resenas', function (Blueprint $table) {
            $table->boolean('es_atipica')->default(false);
            $table->string('revision_atipica', 20)->nullable();
            $table->uuid('revisada_por')->nullable();
            $table->timestamp('revisada_en')->nullable();
            $table->unique('solicitud_id');
        });
    }

    public function down(): void
    {
        Schema::table('resenas', function (Blueprint $table) {
            $table->dropUnique(['solicitud_id']);
            $table->dropColumn(['es_atipica', 'revision_atipica', 'revisada_por', 'revisada_en']);
        });
    }
};
