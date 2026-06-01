<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_servicio', function (Blueprint $table) {
            $table->timestamp('fecha_completado')->nullable()->after('estado');
            $table->boolean('marcado_pagado')->default(false)->after('fecha_completado');
            $table->string('confirmacion_cliente')->nullable()->after('marcado_pagado'); // aceptado | rechazado
            $table->text('queja_cliente')->nullable()->after('confirmacion_cliente');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_servicio', function (Blueprint $table) {
            $table->dropColumn(['fecha_completado', 'marcado_pagado', 'confirmacion_cliente', 'queja_cliente']);
        });
    }
};
