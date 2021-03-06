<?php
  namespace ChiaMgmt\System_Statistics;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  /**
   * The Systems_Statistics_Api class contains every needed methods to show historical data for this chia instance's configured nodes.
   * It manages values regarding historical system load's, memory usage and filesystem usage.
   * This class is used by the webclient to get data.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.1
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class System_Statistics_Api{
    /**
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;
    /**
     * Holds an instance to the Nodes Class.
     * @var Logging_Api
     */
    private $logging_api;
    /**
     * Holds a system config json array.
     * @var array
     */
    private $ini;
    /**
     * Holds an instance to the Webocket Server Class.
     * @var WebSocketServer
     */
    private $server;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(object $server = NULL){
      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this, $server);
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->server = $server;
    }

    /**
     * Returns a history about the nodes reported system load.
     * Function made for: Web/App-Client
     * @param array $data   { "from" : [DateTime], "to" : [DateTime], "node_ids" : [array] }
     * @return array        Returns a status code array with the DBs stored values.
     */
    public function getSystemsLoadHistory(array $data): array
    {
      if(array_key_exists("from", $data) && array_key_exists("to", $data)){
        if(strtotime($data["from"]) &&  strtotime($data["to"]) && new \DateTime($data["from"]) < new \DateTime($data["to"])){
          try{
            if(array_key_exists("node_ids", $data) && is_array($data["node_ids"])){
              $sql = $this->db_api->execute("SELECT n.id, n.hostname, cis.timestamp, cis.load_1min, cis.load_5min, cis.load_15min, cis.cpu_count, cis.cpu_cores FROM nodes n 
                                            INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id
                                            WHERE n.authtype = 2 AND n.id in (?) AND cis.timestamp BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND cis.id mod 10 = 0
                                            ORDER BY timestamp ASC", 
                                            array(implode(",", $data["node_ids"]), $data["from"], $data["to"]));  
            }else{
              $sql = $this->db_api->execute("SELECT n.id, n.hostname, cis.timestamp, cis.load_1min, cis.load_5min, cis.load_15min, cis.cpu_count, cis.cpu_cores FROM nodes n 
                                              INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id
                                              WHERE n.authtype = 2 AND cis.timestamp BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND cis.id mod 10 = 0
                                              ORDER BY timestamp ASC", 
                                              array($data["from"], $data["to"]));
            }

            $historicalLoadData = $sql->fetchAll(\PDO::FETCH_ASSOC);
            $returndata = [];
            foreach($historicalLoadData AS $arrkey => $loaddata){
              if(!array_key_exists($loaddata["id"], $returndata)){
                $returndata[$loaddata["id"]][0] = $loaddata;
              }else{
                array_push($returndata[$loaddata["id"]], $loaddata);
              }
            }

            return array("status" => 0, "message" => "Successfully loaded data between {$data["from"]} and {$data["to"]}.", "data" => $returndata);
          }catch(\Exception $e){
            return $this->logging_api->getErrormessage("001", $e);
          }
        }else{
          return $this->logging_api->getErrormessage("002");
        }
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }

    /**
     * Returns a history about the nodes reported system ram and swap usage.
     * Function made for: Web/App-Client
     * @param array $data   { "from" : [DateTime], "to" : [DateTime], "node_ids" : [array] }
     * @return array        Returns a status code array with the DBs stored values.
     */
    public function getRAMSwapHistory(array $data): array
    {
      if(array_key_exists("from", $data) && array_key_exists("to", $data)){
        if(strtotime($data["from"]) &&  strtotime($data["to"]) && new \DateTime($data["from"]) < new \DateTime($data["to"])){
          try{
            if(array_key_exists("node_ids", $data) && is_array($data["node_ids"])){
              $sql = $this->db_api->execute("SELECT n.id, n.hostname, cis.timestamp, cis.memory_total, cis.memory_free, cis.memory_buffers, cis.memory_cached, cis.memory_shared, cis.swap_total, cis.swap_free FROM nodes n 
                                            INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id
                                            WHERE n.authtype = 2 AND n.id in (?) AND cis.timestamp BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND cis.id mod 10 = 0
                                            ORDER BY timestamp ASC", 
                                            array(implode(",", $data["node_ids"]), $data["from"], $data["to"]));    
            }else{
              $sql = $this->db_api->execute("SELECT n.id, n.hostname, cis.timestamp, cis.memory_total, cis.memory_free, cis.memory_buffers, cis.memory_cached, cis.memory_shared, cis.swap_total, cis.swap_free FROM nodes n 
                                              INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id
                                              WHERE n.authtype = 2 AND cis.timestamp BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND cis.id mod 10 = 0
                                              ORDER BY timestamp ASC", 
                                              array($data["from"], $data["to"]));   
            }
            
            $historicalLoadData = $sql->fetchAll(\PDO::FETCH_ASSOC);
            $returndata = [];
            foreach($historicalLoadData AS $arrkey => $loaddata){
              if(!array_key_exists($loaddata["id"], $returndata)){
                $returndata[$loaddata["id"]][0] = $loaddata;
              }else{
                array_push($returndata[$loaddata["id"]], $loaddata);
              }
            }

            return array("status" => 0, "message" => "Successfully loaded data between {$data["from"]} and {$data["to"]}.", "data" => $returndata);
          }catch(\Exception $e){
            return $this->logging_api->getErrormessage("001", $e);
          }
        }else{
          return $this->logging_api->getErrormessage("002");
        }
      }else{
        //Not all data stated
        return $this->logging_api->getErrormessage("003");
      }
    }

    /**
     * Returns a history about the nodes reported filesystem(s) usage.
     * Function made for: Web/App-Client
     * @param array $data   { "from" : [DateTime], "to" : [DateTime], "node_ids" : [array] }
     * @return array        Returns a status code array with the DBs stored values.
     */
    public function getFilesystemsHistory(array $data): array
    {
      if(array_key_exists("from", $data) && array_key_exists("to", $data)){
        if(strtotime($data["from"]) &&  strtotime($data["to"]) && new \DateTime($data["from"]) < new \DateTime($data["to"])){
          try{
            if(array_key_exists("node_ids", $data) && is_array($data["node_ids"])){
              $sql = $this->db_api->execute("SELECT n.id, n.hostname, cis.timestamp, cisf.device, cisf.size, cisf.used, cisf.avail, cisf.mountpoint FROM nodes n 
                                            INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id
                                            INNER JOIN chia_infra_sysinfo_filesystems cisf ON cisf.sysinfo_id = cis.id
                                            WHERE n.authtype = 2 AND n.id in (?) AND cis.timestamp BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND cis.id mod 10 = 0
                                            ORDER BY timestamp ASC", 
                                            array(implode(",", $data["node_ids"]), $data["from"], $data["to"]));    
            }else{
              $sql = $this->db_api->execute("SELECT n.id, n.hostname, cis.timestamp, cisf.device, cisf.size, cisf.used, cisf.avail, cisf.mountpoint FROM nodes n 
                                              INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id
                                              INNER JOIN chia_infra_sysinfo_filesystems cisf ON cisf.sysinfo_id = cis.id
                                              WHERE n.authtype = 2 AND cis.timestamp BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND cis.id mod 10 = 0
                                              ORDER BY timestamp ASC", 
                                              array($data["from"], $data["to"]));
            }
            
            $historicalFSdata = $sql->fetchAll(\PDO::FETCH_ASSOC);
            $returndata = [];
            foreach($historicalFSdata AS $arrkey => $fsdata){
              if(!array_key_exists($fsdata["id"], $returndata)) $returndata[$fsdata["id"]] = [];
              if(!array_key_exists($fsdata["mountpoint"], $returndata[$fsdata["id"]])){
                $returndata[$fsdata["id"]][$fsdata["mountpoint"]] = [$fsdata];
              }else{
                array_push($returndata[$fsdata["id"]][$fsdata["mountpoint"]], $fsdata);
              }
            }

            return array("status" => 0, "message" => "Successfully loaded data between {$data["from"]} and {$data["to"]}.", "data" => $returndata);
          }catch(\Exception $e){
            return $this->logging_api->getErrormessage("001", $e);
          }
        }else{
          return $this->logging_api->getErrormessage("002");
        }
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }

    /**
     * Returns a history about the nodes reported up/down status and services running stats.
     * Function made for: Web/App-Client
     * @param array $data   { "from" : [DateTime], "to" : [DateTime], "node_ids" : [array] }
     * @return array        Returns a status code array with the DBs stored values.
     */
    public function getNodeUPAndServicesHistory(array $data): array
    {
      if(array_key_exists("from", $data) && array_key_exists("to", $data)){
        if(strtotime($data["from"]) &&  strtotime($data["to"]) && new \DateTime($data["from"]) < new \DateTime($data["to"])){
          try{
            $nodeids = "";
            if(array_key_exists("node_ids", $data) && is_array($data["node_ids"])) $nodeids = "AND nodeid IN (" . implode(",", $data["node_ids"]) . ")";

            $sql = $this->db_api->execute("SELECT nus.nodeid, n.hostname, nus.onlinestatus,  nus.firstreported AS node_firstreported, nus.lastreported AS node_lastreported
                                            FROM nodes_up_status nus
                                            JOIN nodes n ON n.id = nus.nodeid
                                            WHERE nus.firstreported BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) {$nodeids}
                                            ORDER BY nus.firstreported DESC", 
                                            array($data["from"], $data["to"]));

            $historicalNodeUPdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

            $sql = $this->db_api->execute("SELECT nss.nodeid, n.hostname, nss.serviceid, nta.description, nss.servicestate, nss.firstreported AS service_firstreported, nss.lastreported AS service_lastreported
                                            FROM nodes_services_status nss
                                            JOIN nodes n ON n.id = nss.nodeid
                                            JOIN nodetypes_avail nta ON nta.code = nss.serviceid
                                            WHERE nss.firstreported BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) {$nodeids}
                                            ORDER BY nss.firstreported DESC", 
                                            array($data["from"], $data["to"]));

            $historicalServicesdata = $sql->fetchAll(\PDO::FETCH_ASSOC);
            
            $returndata = [];
            foreach($historicalNodeUPdata AS $arrkey => $nodeupinfo){
              if(!array_key_exists($nodeupinfo["nodeid"], $returndata)){
                $returndata[$nodeupinfo["nodeid"]] = [
                  "nodeinfo" => [
                    "nodeid" => $nodeupinfo["nodeid"],
                    "hostname" => $nodeupinfo["hostname"]
                  ],
                  "onlinestatus" => [],
                  "services" => [],
                  "statistics" => [
                    "node" => [
                      "totalSeconds" => 0,
                      "upInSeconds" => 0,
                      "downInSeconds" => 0,
                      "upInPercent" => 0,
                      "downInPercent" => 0
                    ],
                    "services" => []
                  ]
                ];
              }
              $from_time = new \DateTime($nodeupinfo["node_firstreported"]);
              $to_time = new \DateTime($nodeupinfo["node_lastreported"]);
              $seconds_up_down = $to_time->getTimestamp() - $from_time->getTimestamp();
              if($nodeupinfo["onlinestatus"] == 0) $returndata[$nodeupinfo["nodeid"]]["statistics"]["node"]["downInSeconds"] += $seconds_up_down;
              else if($nodeupinfo["onlinestatus"] == 1) $returndata[$nodeupinfo["nodeid"]]["statistics"]["node"]["upInSeconds"] += $seconds_up_down;
              $returndata[$nodeupinfo["nodeid"]]["statistics"]["node"]["totalSeconds"] += $seconds_up_down;

              if(!array_key_exists($arrkey+1, $historicalNodeUPdata)){
                $totalSeconds = $returndata[$nodeupinfo["nodeid"]]["statistics"]["node"]["totalSeconds"];
                $upInSeconds = $returndata[$nodeupinfo["nodeid"]]["statistics"]["node"]["upInSeconds"];
                $downInSeconds = $returndata[$nodeupinfo["nodeid"]]["statistics"]["node"]["downInSeconds"];
                $upInPercent = number_format($upInSeconds / $totalSeconds * 100, 2);
                $downInPercent = number_format($downInSeconds / $totalSeconds * 100, 2);

                $returndata[$nodeupinfo["nodeid"]]["statistics"]["node"]["upInPercent"] = $upInPercent;
                $returndata[$nodeupinfo["nodeid"]]["statistics"]["node"]["downInPercent"] = $downInPercent;
              }

              array_push($returndata[$nodeupinfo["nodeid"]]["onlinestatus"], ["onlinestatus" => $nodeupinfo["onlinestatus"], "node_firstreported" => $nodeupinfo["node_firstreported"], "node_lastreported" => $nodeupinfo["node_lastreported"], "seconds" => $seconds_up_down]);
            }

            foreach($historicalServicesdata AS $arrkey => $serviceinfo){
              if(!array_key_exists($serviceinfo["nodeid"], $returndata)){
                $returndata[$serviceinfo["nodeid"]] = [
                  "nodeinfo" => [
                    "nodeid" => $nodeupinfo["nodeid"],
                    "hostname" => $nodeupinfo["hostname"]
                  ],
                  "onlinestatus" => [],
                  "services" => [],
                  "statistics" => [
                    "node" => [
                      "totalSeconds" => 0,
                      "upInSeconds" => 0,
                      "downInSeconds" => 0,
                      "upInPercent" => 0,
                      "downInPercent" => 0
                    ],
                    "services" => []
                  ]
                ];
              }

              $from_time = new \DateTime($serviceinfo["service_firstreported"]);
              $to_time = new \DateTime($serviceinfo["service_lastreported"]);
              $seconds_last = $to_time->getTimestamp() - $from_time->getTimestamp();

              if(!array_key_exists($serviceinfo["serviceid"], $returndata[$serviceinfo["nodeid"]]["statistics"]["services"])){
                $returndata[$serviceinfo["nodeid"]]["statistics"]["services"][$serviceinfo["serviceid"]] = [
                  "totalSeconds" => 0,
                  "upInSeconds" => 0,
                  "downInSeconds" => 0,
                  "upInPercent" => 0,
                  "downInPercent" => 0
                ];

              }

              if($serviceinfo["servicestate"] == 0) $returndata[$serviceinfo["nodeid"]]["statistics"]["services"][$serviceinfo["serviceid"]]["downInSeconds"] += $seconds_last;
              else if($serviceinfo["servicestate"] == 1) $returndata[$serviceinfo["nodeid"]]["statistics"]["services"][$serviceinfo["serviceid"]]["upInSeconds"] += $seconds_last;
              $returndata[$serviceinfo["nodeid"]]["statistics"]["services"][$serviceinfo["serviceid"]]["totalSeconds"] += $seconds_last;

              if(!array_key_exists($arrkey+1, $historicalServicesdata)){
                foreach([3,4,5] AS $arrkey => $serviceid){
                  if(array_key_exists($serviceid, $returndata[$serviceinfo["nodeid"]]["statistics"]["services"])){
                    $totalSeconds = $returndata[$serviceinfo["nodeid"]]["statistics"]["services"][$serviceid]["totalSeconds"];
                    $upInSeconds = $returndata[$serviceinfo["nodeid"]]["statistics"]["services"][$serviceid]["upInSeconds"];
                    $downInSeconds = $returndata[$serviceinfo["nodeid"]]["statistics"]["services"][$serviceid]["downInSeconds"];
                    $upInPercent = number_format($upInSeconds / $totalSeconds * 100, 2);
                    $downInPercent = number_format($downInSeconds / $totalSeconds * 100, 2);
    
                    $returndata[$serviceinfo["nodeid"]]["statistics"]["services"][$serviceid]["upInPercent"] = $upInPercent;
                    $returndata[$serviceinfo["nodeid"]]["statistics"]["services"][$serviceid]["downInPercent"] = $downInPercent;
                  }
                }
              }
              
              if(!array_key_exists($serviceinfo["serviceid"], $returndata[$serviceinfo["nodeid"]]["services"])) $returndata[$serviceinfo["nodeid"]]["services"][$serviceinfo["serviceid"]] = [];
              array_push($returndata[$serviceinfo["nodeid"]]["services"][$serviceinfo["serviceid"]],[
                "servicestate" => $serviceinfo["servicestate"],
                "service_desc" => $serviceinfo["description"],
                "service_firstreported" => $serviceinfo["service_firstreported"],
                "service_lastreported" => $serviceinfo["service_lastreported"],
                "seconds" => $seconds_last
              ]);
            }

            return array("status" => 0, "message" => "Successfully loaded data between {$data["from"]} and {$data["to"]}.", "data" => $returndata);
          }catch(\Exception $e){
            return $this->logging_api->getErrormessage("001", $e);
          }
        }else{
          return $this->logging_api->getErrormessage("002");
        }
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }
  }
