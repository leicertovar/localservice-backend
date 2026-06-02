<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_servicio', function (Blueprint $table) {
            $table->string('evidencia_cliente')->nullable()->after('queja_cliente');
            $table->string('evidencia_proveedor')->nullable()->after('evidencia_cliente');
            $table->string('estado_disputa')->nullable()->after('evidencia_proveedor'); // pendiente_proveedor | pendiente_admin | resuelto
            $table->string('resolucion_admin')->nullable()->after('estado_disputa');    // aprobado | rechazado
            $table->text('nota_admin')->nullable()->after('resolucion_admin');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_servicio', function (Blueprint $table) {
            $table->dropColumn(['evidencia_cliente','evidencia_proveedor','estado_disputa','resolucion_admin','nota_admin']);
        });
    }
};
