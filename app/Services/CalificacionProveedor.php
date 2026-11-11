<?php

namespace App\Services;

use App\Models\PerfilProveedor;
use App\Models\Resena;

/**
 * Calificación promedio de un proveedor con detección de valores atípicos (método IQR).
 *
 * Las calificaciones de satisfacción (1 a 5) no siguen una distribución normal, por eso se usa
 * el rango intercuartílico y no el Z-score:
 *   atípica si  calificación < Q1 − 1.5·IQR   o   calificación > Q3 + 1.5·IQR
 *
 * Qué pasa con una reseña atípica:
 * - No se borra ni se oculta: el cliente la sigue viendo y el comentario queda disponible.
 * - Sale del promedio del proveedor mientras un administrador no la revise ("investigar el origen").
 * - El administrador decide: 'conservar' (fallo real del servicio, vuelve a contar) o
 *   'excluir' (error o cliente "hater", sigue fuera del promedio).
 * Los errores de escala ("100" en vez de "10") no pueden ocurrir: la API solo acepta 1 a 5.
 */
class CalificacionProveedor
{
    /** Con menos reseñas los cuartiles no son representativos: no se marca ninguna como atípica. */
    public const MUESTRA_MINIMA = 5;

    /**
     * IQR mínimo (en estrellas). Si la mayoría califica igual (p. ej. todo 5), el IQR real es 0 y
     * cualquier 4 saldría atípico; con 1 estrella el rango tolerado queda en Q1−1.5 … Q3+1.5.
     */
    public const IQR_MINIMO = 1.0;

    public const FACTOR_IQR = 1.5;

    /**
     * Recalcula las atípicas y el promedio del proveedor. Llamar tras crear, ocultar,
     * restaurar, eliminar o revisar una reseña.
     */
    public static function recalcular(string $proveedorId): void
    {
        $resenas = Resena::where('proveedor_id', $proveedorId)
            ->where('esta_ocultada', false)
            ->get(['id', 'calificacion', 'es_atipica', 'revision_atipica']);

        $limites = self::limites($resenas->pluck('calificacion')->map(fn ($v) => (float) $v)->all());

        // 1. Marcar o desmarcar atípicas según la distribución actual
        foreach ($resenas as $resena) {
            $atipica = $limites !== null
                && ($resena->calificacion < $limites['inferior'] || $resena->calificacion > $limites['superior']);
            if ($resena->es_atipica !== $atipica) {
                $resena->es_atipica = $atipica;
                $resena->save();
            }
        }

        // 2. Promedio solo con las que cuentan
        $cuentan = $resenas->filter(fn ($r) => self::cuentaEnPromedio($r));

        PerfilProveedor::where('usuario_id', $proveedorId)->update([
            'calificacion' => $cuentan->isEmpty() ? 0 : round($cuentan->avg('calificacion'), 2),
            'total_resenas' => $cuentan->count(),
        ]);
    }

    /**
     * Una reseña cuenta si el admin decidió conservarla, o si no es atípica y no fue excluida.
     */
    public static function cuentaEnPromedio(Resena $resena): bool
    {
        if ($resena->revision_atipica === 'conservar') {
            return true;
        }
        return !$resena->es_atipica && $resena->revision_atipica !== 'excluir';
    }

    /**
     * Límites IQR para un conjunto de calificaciones, o null si la muestra es muy pequeña.
     *
     * @param  float[]  $valores
     * @return array{q1: float, q3: float, iqr: float, inferior: float, superior: float, n: int}|null
     */
    public static function limites(array $valores): ?array
    {
        $n = count($valores);
        if ($n < self::MUESTRA_MINIMA) {
            return null;
        }

        sort($valores);
        $q1 = self::percentil($valores, 0.25);
        $q3 = self::percentil($valores, 0.75);
        $iqr = max($q3 - $q1, self::IQR_MINIMO);

        return [
            'q1' => $q1,
            'q3' => $q3,
            'iqr' => $iqr,
            'inferior' => $q1 - self::FACTOR_IQR * $iqr,
            'superior' => $q3 + self::FACTOR_IQR * $iqr,
            'n' => $n,
        ];
    }

    /** Percentil con interpolación lineal (mismo método por defecto que Excel y NumPy). */
    private static function percentil(array $ordenados, float $p): float
    {
        $posicion = ($p) * (count($ordenados) - 1);
        $abajo = (int) floor($posicion);
        $arriba = (int) ceil($posicion);
        $fraccion = $posicion - $abajo;

        return $ordenados[$abajo] + ($ordenados[$arriba] - $ordenados[$abajo]) * $fraccion;
    }
}
