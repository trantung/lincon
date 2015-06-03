<?php
function intcmp($a, $b) {
    if ($a < $b) return 1;
    if ($a > $b) return -1;
    return 0;
}
// Haversine great circle distance
function globe_distance($lat1, $lng1, $lat2, $lng2, $earthRadius = 6371009) {
    $lat1 = deg2rad($lat1);
    $lng1 = deg2rad($lng1);
    $lat2 = deg2rad($lat2);
    $lng2 = deg2rad($lng2);
    $latDelta = $lat2 - $lat1;
    $lngDelta = $lng2 - $lng1;
    $angle = 2 *
        asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($lat1) * cos($lat2) * pow(sin($lngDelta / 2), 2)
        ));
    return $angle * $earthRadius;
}

function limit_string($string, $length = 50, $extra_mark = '...') {
    return mb_strlen($string) > $length
        ? mb_substr($string, 0, $length). $extra_mark
        : $string;
}
