<?php
  function getServiceStates($nodes_states, $nodeid, $type){
    $statusname = strtolower($type)."status";

    if(is_null($nodes_states)){
      return array("statustext" => "Node not reachable.", "statusicon" => "badge-danger");
    }
    
    if($nodes_states[$nodeid]["onlinestatus"] == 1){
      $statustext = "Node not reachable.";
      $statusicon = "badge-danger";
    }else if($nodes_states[$nodeid]["onlinestatus"] == 0){
      if($nodes_states[$nodeid][$statusname] == 1){
        $statustext = "{$type} service not running.";
        $statusicon = "badge-danger";
      }else if($nodes_states[$nodeid][$statusname] == 0){
        $statustext = "{$type} service running.";
        $statusicon = "badge-success";
      }else{
        $statustext = "Querying service status";
        $statusicon = "badge-secondary";
      }
    }

    return array("statustext" => $statustext, "statusicon" => $statusicon);
  }
?>
