<?php 
    function formatkBytes(int $size, int $precision = 2)
    { 
        if($size == 0) return "0B";
        $base = log($size, 1024);
        $suffixes = array('B','KB', 'MB', 'GB', 'TB');   

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

    function getWARNLevelBadge(int $warnlevel, bool $downtime_active, int $badge_or_overview = 0){
        $downtime_active_string = "";
        if($downtime_active){
            $downtime_active_string .= " (Downtime)";
        }

        if($badge_or_overview == 0) return getLevelBadge($warnlevel, $downtime_active_string);
        if($badge_or_overview == 1) return getOverviewLevel($warnlevel); 
    }

    function getLevelBadge(int $warnlevel, string $downtime_active_string){
        switch ($warnlevel) {
            case 1:
                return "<span class='badge statusbadge badge-success'>OK{$downtime_active_string}</span>";
            case 2:
            return "<span class='badge statusbadge badge-warning'>WARN{$downtime_active_string}</span>";
            case 3:
            return "<span class='badge statusbadge badge-danger'>CRIT{$downtime_active_string}</span>";
            default:
            return "<span class='badge statusbadge badge-secondary'>UNKN{$downtime_active_string}</span>";
        }
    }

    function getOverviewLevel(int $warnlevel){
        switch ($warnlevel) {
            case 1:
                return "<span class='input-group-text service-state bg-success'>OK</span>";
            case 2:
            return "<span class='input-group-text service-state bg-warning'>WARN</span>";
            case 3:
            return "<span class='input-group-text service-state bg-danger'>CRIT</span>";
            default:
            return "<span class='input-group-text service-state bg-secondary'>UNKN</span>";
        }
    }

    function calculateLastCheckedTime(string $start_time){
        $time_unit = ["s","m","h","wk","M"];
        $time_calc = [1, 60, 3600, 604800, 2628000];

        $d1 = strtotime("now");
        $d2 = strtotime($start_time);
        $seconds = abs($d1-$d2);

        foreach($time_calc AS $arrkey => $this_time_calc){
            $time_in_curr_unit = $seconds / $this_time_calc;
            if(strlen(intval($time_in_curr_unit)) <= 2){
            return number_format($time_in_curr_unit, 2) . "{$time_unit[$arrkey]}";
            }
        }
    }
?>