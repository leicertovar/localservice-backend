<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verificación de identidad del proveedor.
 *
 * documentos_verificacion: cédula (frente y reverso), RUT y diploma o certificación. El archivo se
 *   guarda cifrado (AES-256 con la APP_KEY) en el disco privado; aquí solo queda la referencia.
 *
 * verificaciones_proveedor: datos leídos de la cédula (PDF417 / MRZ, cifrados), comparación con el
 *   registro (porcentaje de coincidencia) y los campos que llenará el agente de IA (Gemini) para
 *   aprobar automáticamente o pasar el caso al administrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_verificacion', function (Blueprint $table) {
            $table->id();
            $table->uuid('usuario_id');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            // cedula_frente, cedula_reverso, cedula (frente y reverso en una imagen, registro web), rut, diploma
            $table->string('tipo', 30);
            $table->string('ruta', 255);
            $table->string('mime', 100);
            $table->string('nombre_original', 255)->nullable();
            $table->unsignedInteger('tamano');
            // Huella del archivo original: detecta documentos repetidos entre cuentas
            $table->char('sha256', 64);
            $table->timestamps();
            $table->index(['usuario_id', 'tipo']);
            $table->index('sha256');
        });

        Schema::create('verificaciones_proveedor', function (Blueprint $table) {
            $table->id();
            $table->uuid('usuario_id')->unique();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();

            // pendiente_ia → (aprobada | revision_admin) → aprobada | rechazada
            // Mientras no exista el agente de IA, todo queda en revision_admin.
            $table->string('estado', 30)->default('revision_admin');

            // Datos del registro con los que se compara (cifrados)
            $table->text('numero_documento')->nullable();
            // HMAC del número: permite detectar la misma cédula en otra cuenta sin descifrar
            $table->char('numero_documento_hash', 64)->nullable()->index();
            $table->string('nombre_registro', 255);

            // Lectura automática del reverso: pdf417 (cédula amarilla), mrz (cédula digital) o ninguna
            $table->string('metodo_extraccion', 20)->default('ninguna');
            $table->text('datos_extraidos')->nullable();
            $table->decimal('porcentaje_coincidencia', 5, 2)->nullable();
            $table->json('detalle_coincidencia')->nullable();

            // Agente de IA (pendiente): puntuación 0–100, análisis y modelo usado
            $table->decimal('puntuacion_ia', 5, 2)->nullable();
            $table->json('analisis_ia')->nullable();
            $table->string('modelo_ia', 100)->nullable();
            $table->timestamp('evaluado_ia_en')->nullable();

            // Decisión final: automatica (IA) o admin
            $table->string('decision', 20)->nullable();
            $table->uuid('decidido_por')->nullable();
            $table->timestamp('decidido_en')->nullable();
            $table->text('nota')->nullable();

            $table->timestamps();
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verificaciones_proveedor');
        Schema::dropIfExists('documentos_verificacion');
    }
};
