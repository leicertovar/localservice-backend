<?php

namespace App\Console\Commands;

use App\Models\PerfilProveedor;
use App\Models\VerificacionProveedor;
use App\Services\DocumentosCifrados;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Pasa los documentos de verificación que quedaron en el disco público (/storage/documentos)
 * al almacenamiento cifrado y borra la copia pública. También crea la verificación de los
 * proveedores que no la tienen. Se puede ejecutar varias veces sin duplicar nada.
 *
 *   php artisan documentos:cifrar            (pide confirmación)
 *   php artisan documentos:cifrar --force    (Railway / sin preguntar)
 */
class CifrarDocumentos extends Command
{
    protected $signature = 'documentos:cifrar {--force : No pedir confirmación}';

    protected $description = 'Cifra los documentos de verificación públicos de los proveedores y borra la copia pública';

    /** Columna del perfil → tipo de documento. */
    private const COLUMNAS = [
        'doc_cedula' => 'cedula',
        'doc_rut' => 'rut',
        'doc_diploma' => 'diploma',
        'url_documento' => 'documento',
    ];

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('Se cifrarán los documentos y se borrarán sus copias públicas. ¿Continuar?')) {
            return self::FAILURE;
        }

        $publico = Storage::disk('public');
        $cifrados = 0;
        $faltantes = 0;

        PerfilProveedor::with('usuario')->chunkById(100, function ($perfiles) use ($publico, &$cifrados, &$faltantes) {
            foreach ($perfiles as $perfil) {
                $cambios = [];
                foreach (self::COLUMNAS as $columna => $tipo) {
                    // Valor guardado (sin el accesor que lo convierte en enlace firmado)
                    $valor = $perfil->getRawOriginal($columna);
                    if (!$valor || !str_starts_with($valor, '/storage/')) {
                        continue;
                    }
                    $ruta = substr($valor, strlen('/storage/'));
                    if (!$publico->exists($ruta)) {
                        $this->warn("No existe {$valor} ({$perfil->usuario_id})");
                        $faltantes++;
                        continue;
                    }
                    $documento = DocumentosCifrados::guardarContenido(
                        $publico->get($ruta),
                        $publico->mimeType($ruta) ?: 'application/octet-stream',
                        basename($ruta),
                        $perfil->usuario_id,
                        $tipo
                    );
                    $cambios[$columna] = 'cifrado:' . $documento->id;
                    $publico->delete($ruta);
                    $cifrados++;
                }
                if ($cambios) {
                    $perfil->forceFill($cambios)->saveQuietly();
                }

                // Proveedores registrados antes de la verificación: sin lectura automática
                if ($perfil->usuario && !VerificacionProveedor::where('usuario_id', $perfil->usuario_id)->exists()) {
                    VerificacionProveedor::create([
                        'usuario_id' => $perfil->usuario_id,
                        'estado' => $perfil->usuario->esta_aprobado ? VerificacionProveedor::APROBADA : VerificacionProveedor::REVISION_ADMIN,
                        'decision' => $perfil->usuario->esta_aprobado ? 'admin' : null,
                        'nombre_registro' => trim($perfil->usuario->nombre . ' ' . $perfil->usuario->apellido),
                        'metodo_extraccion' => 'ninguna',
                        'detalle_coincidencia' => ['motivo' => 'registro_anterior'],
                    ]);
                }
            }
        });

        $this->info("Documentos cifrados: {$cifrados}. No encontrados: {$faltantes}.");
        return self::SUCCESS;
    }
}
