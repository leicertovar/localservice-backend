<?php

namespace App\Support;

use App\Models\PerfilProveedor;

/**
 * Distancias en el mapa y si un punto cae dentro del radio de atención de un proveedor.
 */
class ZonaAtencion
{
    /** Radio máximo que puede declarar un proveedor. */
    public const RADIO_MAXIMO_KM = 30;

    /** Distancia en km entre dos coordenadas (fórmula de Haversine). */
    public static function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radioTierra = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $radioTierra * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * ['distancia_km' => 3.2, 'dentro' => true, 'radio_km' => 8], o null si falta
     * la ubicación base del proveedor, su radio o el punto a comparar.
     */
    public static function evaluar(?PerfilProveedor $perfil, $latitud, $longitud): ?array
    {
        if (!$perfil || $perfil->latitud === null || $perfil->longitud === null || !$perfil->radio_cobertura_km
            || $latitud === null || $longitud === null) {
            return null;
        }

        $distancia = self::distanciaKm((float) $perfil->latitud, (float) $perfil->longitud, (float) $latitud, (float) $longitud);

        return [
            'distancia_km' => round($distancia, 1),
            'dentro' => $distancia <= $perfil->radio_cobertura_km,
            'radio_km' => (int) $perfil->radio_cobertura_km,
        ];
    }
}
