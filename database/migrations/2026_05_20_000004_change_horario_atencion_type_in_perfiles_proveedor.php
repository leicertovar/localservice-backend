<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('perfiles_proveedor', function (Blueprint $table) {
            $table->text('horario_atencion')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('perfiles_proveedor', function (Blueprint $table) {
            $table->string('horario_atencion', 255)->nullable()->change();
        });
    }
};
