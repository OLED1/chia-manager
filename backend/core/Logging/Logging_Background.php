<?php
    if(count($argv) == 4){
        $ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
        $loglevel = $argv[1];
        $errorcode = $argv[2];
        $text = $argv[3];
   
        $projectroot = realpath(__DIR__."/../../../");
        $logpath = "{$projectroot}/logs/api.log";
        if(array_key_exists("log_root", $ini)){
            $logpath = "{$projectroot}{$ini["log_root"]}/api.log";
        }

        $loggingfile = fopen($logpath, 'a') or die(json_encode(array("status" => 1, "message" => "Unable to open file!")));
        $text = str_replace(PHP_EOL, '', $text);
        $errortext = date("Y/m/d H:i:s") . ";" . $loglevel . ";" . $errorcode . ";". $text . ";";

        fwrite($loggingfile, $errortext."\n");
        fclose($loggingfile);

        echo json_encode(array("status" => 0, "message" => "Successfully wrote message '{$errortext}' to log file '{$logpath}' at " . date("Y-m-d H:i:s") . "."));
    }else{
        echo json_encode(array("status" => 1, "NOT ALL PARAMTERS NEEDED STATED. ABORTING...\n"));
    }
?>