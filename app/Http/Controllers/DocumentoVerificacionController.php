<?php

namespace App\Http\Controllers;

use App\Models\DocumentoVerificacion;
use App\Services\DocumentosCifrados;

/**
 * Entrega un documento de verificación descifrado. Solo con un enlace firmado y vigente
 * (lo genera la API para el administrador): sin firma responde 403.
 */
class DocumentoVerificacionController extends Controller
{
    public function ver(DocumentoVerificacion $documento)
    {
        $extension = match ($documento->mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            default => 'jpg',
        };

        return response(DocumentosCifrados::leer($documento), 200, [
            'Content-Type' => $documento->mime,
            'Content-Disposition' => 'inline; filename="' . $documento->tipo . '-' . $documento->id . '.' . $extension . '"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
