<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();
            $table->uuid('emisor_id');
            $table->uuid('receptor_id');
            $table->text('contenido')->nullable();
            $table->string('tipo')->default('texto'); // texto, imagen, video, documento
            $table->string('url_archivo')->nullable();
            $table->string('nombre_archivo')->nullable();
            $table->boolean('leido')->default(false);
            $table->timestamps();

            $table->foreign('emisor_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->foreign('receptor_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->index(['emisor_id', 'receptor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes');
    }
};
