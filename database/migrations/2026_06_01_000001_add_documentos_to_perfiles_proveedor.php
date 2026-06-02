<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perfiles_proveedor', function (Blueprint $table) {
            $table->string('doc_cedula')->nullable()->after('url_documento');
            $table->string('doc_rut')->nullable()->after('doc_cedula');
            $table->string('doc_diploma')->nullable()->after('doc_rut');
        });
    }

    public function down(): void
    {
        Schema::table('perfiles_proveedor', function (Blueprint $table) {
            $table->dropColumn(['doc_cedula', 'doc_rut', 'doc_diploma']);
        });
    }
};
