<?php

namespace App\Services;

/**
 * Compara los datos leídos de la cédula (PDF417 o MRZ) con los del registro y calcula el
 * porcentaje de coincidencia que se guarda en la verificación (y que luego usará el agente de IA).
 *
 *  - Número de cédula: coincide exactamente o no (50 %).
 *  - Nombre (50 %): qué tanto de lo que escribió la persona aparece en la cédula (75 %) y qué
 *    tanto del nombre completo de la cédula escribió (25 %). Así "Edgar García" frente a
 *    "EDGAR ANDRÉS GARCÍA LÓPEZ" puntúa alto, pero un nombre inventado no.
 *    Se toleran tildes y un error de una letra en palabras de 5 o más letras.
 */
class ComparadorIdentidad
{
    public const PESO_NUMERO = 0.5;
    public const PESO_NOMBRE = 0.5;

    /**
     * @param  array{numero?: string, nombres?: string, apellidos?: string}|null  $leidos
     * @return array{0: float|null, 1: array<string, mixed>}  [porcentaje, detalle]
     */
    public static function comparar(string $nombreRegistro, ?string $numeroRegistro, ?array $leidos): array
    {
        if (!$leidos || empty($leidos['numero'])) {
            return [null, ['motivo' => 'sin_lectura_automatica']];
        }

        $numeroLeido = preg_replace('/\D/', '', (string) $leidos['numero']);
        $numeroEscrito = preg_replace('/\D/', '', (string) $numeroRegistro);
        $numeroCoincide = $numeroEscrito !== '' && $numeroEscrito === $numeroLeido;

        $escritas = self::palabras($nombreRegistro);
        $documento = self::palabras(($leidos['nombres'] ?? '') . ' ' . ($leidos['apellidos'] ?? ''));
        $encontradas = count(array_filter($escritas, fn ($p) => self::aparece($p, $documento)));
        $cubiertas = count(array_filter($documento, fn ($p) => self::aparece($p, $escritas)));
        $nombre = $escritas && $documento
            ? 100 * (0.75 * $encontradas / count($escritas) + 0.25 * $cubiertas / count($documento))
            : 0.0;

        $porcentaje = $numeroEscrito !== ''
            ? self::PESO_NUMERO * ($numeroCoincide ? 100 : 0) + self::PESO_NOMBRE * $nombre
            : $nombre;

        return [round($porcentaje, 2), [
            'numero_coincide' => $numeroCoincide,
            'porcentaje_nombre' => round($nombre, 2),
            'palabras_registro' => count($escritas),
            'palabras_encontradas' => $encontradas,
            'palabras_documento' => count($documento),
        ]];
    }

    /** "José  García-López" → ["JOSE", "GARCIA", "LOPEZ"] */
    public static function palabras(string $texto): array
    {
        $sinTildes = strtr(mb_strtoupper($texto, 'UTF-8'), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        $partes = preg_split('/[^A-Z]+/', $sinTildes, -1, PREG_SPLIT_NO_EMPTY);
        // "DE", "DEL", "LA" no identifican a nadie
        return array_values(array_filter($partes, fn ($p) => strlen($p) > 1 && !in_array($p, ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'Y'], true)));
    }

    private static function aparece(string $palabra, array $lista): bool
    {
        foreach ($lista as $otra) {
            if ($palabra === $otra) return true;
            if (strlen($palabra) >= 5 && strlen($otra) >= 5 && levenshtein($palabra, $otra) <= 1) return true;
        }
        return false;
    }
}
