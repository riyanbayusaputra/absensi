<?php

namespace App\Services;

use App\Models\Employee;

class LocationService
{
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /** @return array{0: \App\Models\OfficeLocation, 1: int} lokasi paling relevan dan jaraknya (meter) */
    public function nearestAllowed(Employee $employee, float $lat, float $lng): array
    {
        $best    = null;
        $bestGap = INF;
        $bestDist = 0;

        foreach ($employee->locations()->where('is_active', true)->get() as $loc) {
            $d   = self::haversine($lat, $lng, (float) $loc->latitude, (float) $loc->longitude);
            $gap = $d - $loc->radius_m;          // negatif = di dalam radius
            if ($gap < $bestGap) {
                $best = $loc;
                $bestGap = $gap;
                $bestDist = $d;
            }
        }

        return [$best, (int) round($bestDist)];
    }
}