<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * App móvil:
     * - perfiles_proveedor: zona de cobertura elegida en el registro (radio y barrios; el
     *   municipio se guarda en la columna existente "ciudad").
     * - servicios: zona donde se ofrece cada servicio y si la tarifa es fija o por hora.
     */
    public function up(): void
    {
        Schema::table('perfiles_proveedor', function (Blueprint $table) {
            $table->unsignedSmallInteger('radio_cobertura_km')->nullable();
            $table->json('barrios_cobertura')->nullable();
        });

        Schema::table('servicios', function (Blueprint $table) {
            $table->string('zona')->nullable();
            $table->string('tipo_cobro')->default('fijo'); // fijo | hora
        });
    }

    public function down(): void
    {
        Schema::table('perfiles_proveedor', function (Blueprint $table) {
            $table->dropColumn(['radio_cobertura_km', 'barrios_cobertura']);
        });

        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn(['zona', 'tipo_cobro']);
        });
    }
};
