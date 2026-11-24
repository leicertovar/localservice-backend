<?php

namespace App\Services;

use App\Models\DocumentoVerificacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Documentos de verificación del proveedor guardados cifrados.
 *
 * - Se cifran con Crypt (AES-256-CBC + MAC, clave APP_KEY) antes de escribirlos.
 * - Van al disco privado (storage/app/private), que no se sirve por web.
 * - El administrador los ve con un enlace firmado y temporal (DocumentoVerificacion::urlFirmada).
 *
 * Importante: si cambia la APP_KEY, los documentos ya guardados no se pueden descifrar.
 */
class DocumentosCifrados
{
    private const DISCO = 'local';
    private const CARPETA = 'documentos-verificacion';

    public static function guardar(UploadedFile $archivo, string $usuarioId, string $tipo): DocumentoVerificacion
    {
        return self::guardarContenido(
            (string) file_get_contents($archivo->getRealPath()),
            $archivo->getMimeType() ?: 'application/octet-stream',
            mb_substr($archivo->getClientOriginalName(), 0, 200),
            $usuarioId,
            $tipo
        );
    }

    public static function guardarContenido(string $contenido, string $mime, ?string $nombre, string $usuarioId, string $tipo): DocumentoVerificacion
    {
        $ruta = self::CARPETA . '/' . $usuarioId . '/' . Str::uuid() . '.enc';
        Storage::disk(self::DISCO)->put($ruta, Crypt::encryptString($contenido));

        return DocumentoVerificacion::create([
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'ruta' => $ruta,
            'mime' => $mime,
            'nombre_original' => $nombre,
            'tamano' => strlen($contenido),
            'sha256' => hash('sha256', $contenido),
        ]);
    }

    /** Contenido original (descifrado). */
    public static function leer(DocumentoVerificacion $documento): string
    {
        return Crypt::decryptString(Storage::disk(self::DISCO)->get($documento->ruta));
    }

    /** Borra del disco todos los documentos de un usuario (al rechazarlo o eliminar su cuenta). */
    public static function eliminarDeUsuario(string $usuarioId): void
    {
        Storage::disk(self::DISCO)->deleteDirectory(self::CARPETA . '/' . $usuarioId);
    }
}
