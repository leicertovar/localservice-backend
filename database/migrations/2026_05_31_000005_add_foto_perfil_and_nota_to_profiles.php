<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Foto de perfil para proveedores
        Schema::table('perfiles_proveedor', function (Blueprint $table) {
            $table->string('foto_perfil')->nullable()->after('url_documento');
            $table->text('nota_rechazo')->nullable()->after('foto_perfil');
        });
    }

    public function down(): void
    {
        Schema::table('perfiles_proveedor', function (Blueprint $table) {
            $table->dropColumn(['foto_perfil', 'nota_rechazo']);
        });
    }
};
