<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración para añadir la columna ciudad.
     */
    public function up(): void
    {
        Schema::table('perfiles_proveedor', function (Blueprint $table) {
            $table->string('ciudad')->nullable();
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table('perfiles_proveedor', function (Blueprint $table) {
            $table->dropColumn('ciudad');
        });
    }
};
