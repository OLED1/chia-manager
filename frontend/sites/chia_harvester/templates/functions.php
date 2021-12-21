<?php
    function format_spaces(float $size, string $unit , int $precision = 2): string
    {
        if($size == 0) return "0 {$unit}";
        $suffixes = array('Byte','KiB', 'MiB', 'GiB', 'TiB','EiB');
        foreach($suffixes AS $arrkey => $thisunit){
            if($thisunit == $unit){
                break;
            }
            array_splice($suffixes,$arrkey,1);
        }
        $base = log($size, 1024);

        return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
    }

    function format_plot_status(string $last_reported): string
    {
        $now = date_create();
        $last_reported = date_create($last_reported);
        $difference = date_diff($now, $last_reported); 

        $minutes = $difference->days * 24 * 60;
        $minutes += $difference->h * 60;
        $minutes += $difference->i;

        if($minutes <= 10){
            return "<i class='fas fa-check-circle text-success' data-toggle='tooltip' data-placement='top' title='OK - Reported less than 10 min ago.'></i>";
        }else if($minutes > 10 && $minutes <= 15){
            return "<i class='fas fa-exclamation-triangle text-warning' data-toggle='tooltip' data-placement='top' title='WARNING - Not reported since {$minutes} minutes.'></i>";
        }else{
            return "<i class='fas fa-exclamation-circle text-danger' data-toggle='tooltip' data-placement='top' title='CRITICAL - Not reported since {$minutes} minutes.'></i>";
        }
    }
?>