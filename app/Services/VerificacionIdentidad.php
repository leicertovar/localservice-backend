<?php

namespace App\Services;

use App\Models\Usuario;
use App\Models\VerificacionProveedor;
use Illuminate\Http\Request;

/**
 * Registro de la verificación de identidad de un proveedor nuevo:
 * guarda los documentos cifrados, normaliza los datos leídos de la cédula en el teléfono
 * (PDF417 o MRZ), los compara con el registro y deja la verificación lista para el agente de IA.
 */
class VerificacionIdentidad
{
    /** Campo del formulario → tipo de documento. */
    private const DOCUMENTOS = [
        'doc_cedula_frente' => 'cedula_frente',
        'doc_cedula_reverso' => 'cedula_reverso',
        'doc_cedula' => 'cedula', // registro web: frente y reverso en un solo archivo
        'doc_rut' => 'rut',
        'doc_diploma' => 'diploma',
    ];

    /**
     * @return array<string, string|null> referencias para las columnas doc_* del perfil ("cifrado:{id}")
     */
    public static function registrar(Usuario $usuario, Request $request): array
    {
        $documentos = [];
        foreach (self::DOCUMENTOS as $campo => $tipo) {
            if ($request->hasFile($campo)) {
                $documentos[$tipo] = DocumentosCifrados::guardar($request->file($campo), $usuario->id, $tipo);
            }
        }

        $leidos = self::datosLeidos($request->input('datos_documento'));
        $numero = preg_replace('/\D/', '', (string) $request->input('numero_documento')) ?: null;
        $nombreRegistro = trim($usuario->nombre . ' ' . $usuario->apellido);
        [$porcentaje, $detalle] = ComparadorIdentidad::comparar($nombreRegistro, $numero, $leidos);

        // La misma cédula en otra cuenta es una señal fuerte para la revisión
        $huella = VerificacionProveedor::huellaNumero($numero ?? ($leidos['numero'] ?? null));
        if ($huella) {
            $detalle['numero_en_otra_cuenta'] = VerificacionProveedor::where('numero_documento_hash', $huella)
                ->where('usuario_id', '!=', $usuario->id)
                ->exists();
        }

        VerificacionProveedor::create([
            'usuario_id' => $usuario->id,
            // Sin el agente de IA, todas las solicitudes las revisa el administrador
            'estado' => VerificacionProveedor::REVISION_ADMIN,
            'numero_documento' => $numero,
            'numero_documento_hash' => $huella,
            'nombre_registro' => $nombreRegistro,
            'metodo_extraccion' => $leidos['fuente'] ?? 'ninguna',
            'datos_extraidos' => $leidos,
            'porcentaje_coincidencia' => $porcentaje,
            'detalle_coincidencia' => $detalle,
        ]);

        $ref = fn (?string $tipo) => $tipo && isset($documentos[$tipo]) ? 'cifrado:' . $documentos[$tipo]->id : null;
        return [
            'doc_cedula' => $ref(isset($documentos['cedula_frente']) ? 'cedula_frente' : 'cedula'),
            'doc_rut' => $ref('rut'),
            'doc_diploma' => $ref('diploma'),
        ];
    }

    /**
     * Datos que el teléfono leyó del reverso, limpios y con solo los campos esperados
     * (el agente de IA los volverá a verificar contra las fotos).
     */
    public static function datosLeidos(?string $json): ?array
    {
        $d = $json ? json_decode($json, true) : null;
        if (!is_array($d) || !in_array($d['fuente'] ?? null, ['pdf417', 'mrz'], true)) {
            return null;
        }
        $texto = fn ($v, $max = 100) => mb_substr(trim((string) $v), 0, $max) ?: null;
        $numero = preg_replace('/\D/', '', (string) ($d['numero'] ?? ''));
        $fecha = (string) ($d['fechaNacimiento'] ?? '');
        if (strlen($numero) < 5 || strlen($numero) > 12) {
            return null;
        }
        return [
            'fuente' => $d['fuente'],
            'lectura' => 'dispositivo',
            'numero' => $numero,
            'nombres' => $texto($d['nombres'] ?? ''),
            'apellidos' => $texto($d['apellidos'] ?? ''),
            'fecha_nacimiento' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : null,
            'sexo' => in_array($d['sexo'] ?? null, ['M', 'F'], true) ? $d['sexo'] : null,
            'rh' => $texto($d['rh'] ?? '', 3),
            'verificado' => (bool) ($d['verificado'] ?? false),
        ];
    }
}
