<?php

namespace App\Services\Time;

final class Geo
{
    /** Great-circle distance in metres (haversine). */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round(2 * $r * asin(min(1, sqrt($a))));
    }

    public static function human(int $metres): string
    {
        return $metres >= 1000 ? number_format($metres / 1000, 1).' km' : $metres.' m';
    }
}
