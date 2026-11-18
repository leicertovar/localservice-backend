<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Punto base del proveedor en el mapa: centro de su radio de atención.
 * Con él se calcula si una solicitud (o el cliente) está dentro de su zona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perfiles_proveedor', function (Blueprint $table) {
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('perfiles_proveedor', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud']);
        });
    }
};
