<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/** Documento de verificación del proveedor, guardado cifrado (ver App\Services\DocumentosCifrados). */
class DocumentoVerificacion extends Model
{
    protected $table = 'documentos_verificacion';

    protected $fillable = ['usuario_id', 'tipo', 'ruta', 'mime', 'nombre_original', 'tamano', 'sha256'];

    /** La ruta interna y la huella no salen de la API. */
    protected $hidden = ['ruta', 'sha256'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }

    /**
     * Enlace temporal (30 min) para que el administrador lo vea sin exponer el archivo.
     * Es relativo ("/api/…"): el panel web le antepone la URL de la API.
     */
    public function urlFirmada(int $minutos = 30): string
    {
        // El formato va dentro de la firma: el panel decide si muestra la imagen o el enlace al PDF
        $formato = match ($this->mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            default => 'jpg',
        };
        return URL::temporarySignedRoute('documentos.ver', now()->addMinutes($minutos), ['documento' => $this->id, 'formato' => $formato], absolute: false);
    }
}
