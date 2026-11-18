<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fotos de reseñas y servicios: reglas de validación, guardado en el disco público y borrado.
 */
class Imagenes
{
    /** Máximo de fotos por reseña o por servicio. */
    public const MAXIMO = 3;

    /** Reglas para un campo `imagenes[]` (JPG, PNG o WEBP de hasta 5 MB). */
    public static function reglas(int $maximo = self::MAXIMO): array
    {
        return [
            'imagenes' => "nullable|array|max:{$maximo}",
            'imagenes.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    public static function mensajes(int $maximo = self::MAXIMO): array
    {
        return [
            'imagenes.max' => $maximo === 1 ? 'Puedes subir 1 foto más.' : "Puedes subir máximo {$maximo} fotos.",
            'imagenes.*.image' => 'Cada archivo debe ser una foto.',
            'imagenes.*.mimes' => 'Usa fotos JPG, PNG o WEBP.',
            'imagenes.*.max' => 'Cada foto puede pesar máximo 5 MB.',
        ];
    }

    /** Guarda la foto con un nombre aleatorio y devuelve la ruta pública (/storage/...). */
    public static function guardar(UploadedFile $archivo, string $carpeta): string
    {
        $nombre = Str::uuid() . '.' . strtolower($archivo->guessExtension() ?: 'jpg');
        $ruta = $archivo->storeAs($carpeta, $nombre, 'public');

        return '/storage/' . $ruta;
    }

    /** Borra del disco una foto guardada con `guardar`. */
    public static function borrar(?string $rutaPublica): void
    {
        if ($rutaPublica && str_starts_with($rutaPublica, '/storage/')) {
            Storage::disk('public')->delete(substr($rutaPublica, strlen('/storage/')));
        }
    }
}
