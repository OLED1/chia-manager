<?php
    $format_spaces = function(float $bytesize, $precision = 3){
        if($bytesize == 0) return "0 Byte";
        $base = log($bytesize, 1024);
        $suffixes = array('', 'Byte', 'KiB', 'MiB', 'GiB', 'TiB','EiB');

        return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
    };

    $calc_time = function(int $minutes){
        if($minutes < 0) return "Never (no plots)";

        $years = floor($minutes / 525600);
        $minutes -= ($years * 525600);

        $months = floor($minutes / 43800);
        $minutes -= ($months * 43800);

        $weeks = floor($minutes / 10080);
        $minutes -= ($weeks * 10080);

        $days = floor($minutes / 1440);
        $minutes -= ($days * 1440);

        $hours = floor($minutes / 60);
        $minutes -= ($hours * 60);

        $values = array(
            'year' => $years,
            'month' => $months,
            'week'   => $weeks,
            'day'    => $days,
            'hour'   => $hours,
            'minute' => $minutes
        );

        $parts = array();

        foreach ($values as $text => $value) {
            if ($value > 0) {
                $parts[] = $value . ' ' . $text . ($value > 1 ? 's' : '');
            }
        }

        return implode(' ', $parts);
    };

    $calc_xch = function(int $mojos){
        $mojo_def = 1000000000000;
        return $mojos / 1000000000000;
    };