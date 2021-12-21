<?php
  function getServiceStates($nodes_states, $serviceid): array
  {
    if(is_null($nodes_states)){
      return array("statustext" => "Node not reachable.", "statusicon" => "badge-danger");
    }
   
    if($nodes_states["onlinestatus"]["status"] == 0){
      $statustext = "Node not reachable";
      $statusicon = "badge-danger";
    }else if($nodes_states["onlinestatus"]["status"] == 1){
      $servicestate = $nodes_states["services"][$serviceid]["servicestate"];
      $servicedesc = $nodes_states["services"][$serviceid]["service_desc"];
      if($servicestate == 0){
        $statustext = "{$servicedesc} service not running";
        $statusicon = "badge-danger";
      }else if($servicestate == 1){
        $statustext = "{$servicedesc} service running";
        $statusicon = "badge-success";
      }else{
        $statustext = "{$servicedesc} service state unknown";
        $statusicon = "badge-warning";
      }
    }

    return array("statustext" => $statustext, "statusicon" => $statusicon);
  }

  function format_spaces(float $bytesize, $precision = 2): string
  {
    if($bytesize == 0) return "0 byte";
    $base = log($bytesize, 1024);
    $suffixes = array('byte','kiB','MiB','GiB','TiB','EiB');

    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
  }
?>
