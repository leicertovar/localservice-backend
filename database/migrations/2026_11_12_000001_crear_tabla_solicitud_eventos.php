<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de estados de cada solicitud (línea de tiempo del detalle).
 * Estados: pendiente, cotizado, aceptada, en_curso, completada, rechazada (por el proveedor)
 * y cancelada (por el cliente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes_servicio')->cascadeOnDelete();
            $table->string('estado', 30);
            $table->uuid('actor_id')->nullable();
            $table->string('nota', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['solicitud_id', 'created_at']);
        });

        // Las solicitudes existentes arrancan con su creación y, si ya avanzaron, su estado actual.
        DB::statement("
            INSERT INTO solicitud_eventos (solicitud_id, estado, actor_id, created_at)
            SELECT id, 'pendiente', cliente_id, created_at FROM solicitudes_servicio
        ");
        DB::statement("
            INSERT INTO solicitud_eventos (solicitud_id, estado, actor_id, created_at)
            SELECT id, estado, NULL, COALESCE(fecha_completado, updated_at)
            FROM solicitudes_servicio WHERE estado <> 'pendiente'
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_eventos');
    }
};
