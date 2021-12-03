<?php
  function getServiceStates($nodes_states, $nodeid, $type){
    $statusname = strtolower($type)."status";

    if(is_null($nodes_states)){
      return array("statustext" => "Node not reachable.", "statusicon" => "badge-danger");
    }
    
    if($nodes_states[$nodeid]["onlinestatus"] == 1){
      $statustext = "Node not reachable";
      $statusicon = "badge-danger";
    }else if($nodes_states[$nodeid]["onlinestatus"] == 0){
      if($nodes_states[$nodeid][$statusname] == 1){
        $statustext = "{$type} service not running";
        $statusicon = "badge-danger";
      }else if($nodes_states[$nodeid][$statusname] == 0){
        $statustext = "{$type} service running";
        $statusicon = "badge-success";
      }else{
        $statustext = "Querying service status";
        $statusicon = "badge-secondary";
      }
    }

    return array("statustext" => $statustext, "statusicon" => $statusicon);
  }

  function format_spaces(float $bytesize, $precision = 3){
    if($bytesize == 0) return "0 Byte";
    $base = log($bytesize, 1024);
    $suffixes = array('', 'Byte', 'KiB', 'MiB', 'GiB', 'TiB','EiB');

    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
  }
?>
