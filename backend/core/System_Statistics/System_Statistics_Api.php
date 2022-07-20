<?php
  namespace ChiaMgmt\System_Statistics;
  use React\Promise;
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
    public function getSystemsLoadHistory(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("from", $data) && array_key_exists("to", $data)){
          if(strtotime($data["from"]) &&  strtotime($data["to"]) && new \DateTime($data["from"]) < new \DateTime($data["to"])){
            if(array_key_exists("node_ids", $data) && is_array($data["node_ids"])){
              $load_history = Promise\resolve((new DB_Api())->execute("SELECT n.id, n.hostname, cis.timestamp, ciscl.load_1_min, ciscl.load_5_min, ciscl.load_15_min, cis.cpu_count, cis.cpu_cores FROM nodes n 
                                                                        INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id
                                                                        LEFT JOIN chia_infra_sysinfo_cpu_load ciscl ON ciscl.sysinfo_id = cis.id
                                                                        WHERE n.authtype = 2 AND n.id in (?) AND cis.timestamp BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND cis.id mod 10 = 0
                                                                        ORDER BY timestamp ASC", 
                                            array(implode(",", $data["node_ids"]), $data["from"], $data["to"])));  
            }else{
              $load_history = Promise\resolve((new DB_Api())->execute("SELECT n.id, n.hostname, cis.timestamp, ciscl.load_1_min, ciscl.load_5_min, ciscl.load_15_min, cis.cpu_count, cis.cpu_cores FROM nodes n 
                                                                        INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id
                                                                        LEFT JOIN chia_infra_sysinfo_cpu_load ciscl ON ciscl.sysinfo_id = cis.id
                                                                        WHERE n.authtype = 2 AND cis.timestamp BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND cis.id mod 10 = 0
                                                                        ORDER BY timestamp ASC", 
                                              array($data["from"], $data["to"])));
            }

            $load_history->then(function($load_history_returned) use($resolve, $data){
              $returndata = [];

              foreach($load_history_returned->resultRows AS $arrkey => $loaddata){
                if(!array_key_exists($loaddata["id"], $returndata)){
                  $returndata[$loaddata["id"]][0] = $loaddata;
                }else{
                  array_push($returndata[$loaddata["id"]], $loaddata);
                }
              }
  
              $resolve(array("status" => 0, "message" => "Successfully loaded data between {$data["from"]} and {$data["to"]}.", "data" => $returndata));
            })->otherwise(function(\Exception $e) use($resolve){
              $resolve($this->logging_api->getErrormessage("getSystemsLoadHistory", "001", $e));
            });
          }else{
            $resolve($this->logging_api->getErrormessage("getSystemsLoadHistory", "002"));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("getSystemsLoadHistory", "003"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns a history about the nodes reported system ram and swap usage.
     * Function made for: Web/App-Client
     * @param array $data   { "from" : [DateTime], "to" : [DateTime], "node_ids" : [array] }
     * @return array        Returns a status code array with the DBs stored values.
     */
    public function getRAMSwapHistory(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("from", $data) && array_key_exists("to", $data)){
          if(strtotime($data["from"]) &&  strtotime($data["to"]) && new \DateTime($data["from"]) < new \DateTime($data["to"])){
            if(array_key_exists("node_ids", $data) && is_array($data["node_ids"])){
              $ram_swap_history = Promise\resolve((new DB_Api())->execute("SELECT n.id, n.hostname, cis.timestamp, cimu.memory_total, cimu.memory_free, cimu.memory_buffers, cimu.memory_cached, cimu.memory_shared, cisu.swap_total, cisu.swap_free FROM nodes n 
                                                                            INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id
                                                                            LEFT JOIN chia_infra_swap_usage cisu ON cisu.sysinfo_id = cis.id
                                                                            LEFT JOIN chia_infra_memory_usage cimu ON cimu.sysinfo_id = cis.id
                                                                            WHERE n.authtype = 2 AND n.id in (?) AND cis.timestamp BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND cis.id mod 10 = 0
                                                                            ORDER BY timestamp ASC", 
                                                    array(implode(",", $data["node_ids"]), $data["from"], $data["to"])));
            }else{
              $ram_swap_history = Promise\resolve((new DB_Api())->execute("SELECT n.id, n.hostname, cis.timestamp, cimu.memory_total, cimu.memory_free, cimu.memory_buffers, cimu.memory_cached, cimu.memory_shared, cisu.swap_total, cisu.swap_free FROM nodes n 
                                                                            INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id
                                                                            LEFT JOIN chia_infra_swap_usage cisu ON cisu.sysinfo_id = cis.id
                                                                            LEFT JOIN chia_infra_memory_usage cimu ON cimu.sysinfo_id = cis.id
                                                                            WHERE n.authtype = 2 AND cis.timestamp BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND cis.id mod 10 = 0
                                                                            ORDER BY timestamp ASC", 
                                                    array($data["from"], $data["to"])));
            }

            $ram_swap_history->then(function($ram_swap_history_returned) use($resolve, $data){
              $returndata = [];

              foreach($ram_swap_history_returned->resultRows AS $arrkey => $ram_swap_data){
                if(!array_key_exists($ram_swap_data["id"], $returndata)){
                  $returndata[$ram_swap_data["id"]][0] = $ram_swap_data;
                }else{
                  array_push($returndata[$ram_swap_data["id"]], $ram_swap_data);
                }
              }
  
              $resolve(array("status" => 0, "message" => "Successfully loaded data between {$data["from"]} and {$data["to"]}.", "data" => $returndata));
            })->otherwise(function(\Exception $e) use($resolve){
              $resolve($this->logging_api->getErrormessage("getRAMSwapHistory", "001", $e));
            });
          }else{
            $resolve($this->logging_api->getErrormessage("getRAMSwapHistory", "002"));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("getRAMSwapHistory", "003"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns a history about the nodes reported filesystem(s) usage.
     * Function made for: Web/App-Client
     * @param array $data   { "from" : [DateTime], "to" : [DateTime], "node_ids" : [array] }
     * @return array        Returns a status code array with the DBs stored values.
     */
    public function getFilesystemsHistory(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("from", $data) && array_key_exists("to", $data)){
          if(strtotime($data["from"]) &&  strtotime($data["to"]) && new \DateTime($data["from"]) < new \DateTime($data["to"])){
            if(array_key_exists("node_ids", $data) && is_array($data["node_ids"])){
              $filesystems_history = Promise\resolve((new DB_Api())->execute("SELECT n.id, n.hostname, cis.timestamp, cisf.device, cisf.size, cisf.used, cisf.avail, cisf.mountpoint FROM nodes n 
                                                                            INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id
                                                                            INNER JOIN chia_infra_sysinfo_filesystems cisf ON cisf.sysinfo_id = cis.id
                                                                            WHERE n.authtype = 2 AND n.id in (?) AND cis.timestamp BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND cis.id mod 10 = 0
                                                                            ORDER BY timestamp ASC", 
                                                    array(implode(",", $data["node_ids"]), $data["from"], $data["to"])));
            }else{
              $filesystems_history = Promise\resolve((new DB_Api())->execute("SELECT n.id, n.hostname, cis.timestamp, cisf.device, cisf.size, cisf.used, cisf.avail, cisf.mountpoint FROM nodes n 
                                                                            INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id
                                                                            INNER JOIN chia_infra_sysinfo_filesystems cisf ON cisf.sysinfo_id = cis.id
                                                                            WHERE n.authtype = 2 AND cis.timestamp BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND cis.id mod 10 = 0
                                                                            ORDER BY timestamp ASC", 
                                                    array($data["from"], $data["to"])));
            }

            $filesystems_history->then(function($filesystems_history_returned) use($resolve, $data){
              $returndata = [];

              foreach($filesystems_history_returned->resultRows AS $arrkey => $fsdata){
                if(!array_key_exists($fsdata["id"], $returndata)) $returndata[$fsdata["id"]] = [];
                if(!array_key_exists($fsdata["mountpoint"], $returndata[$fsdata["id"]])){
                  $returndata[$fsdata["id"]][$fsdata["mountpoint"]] = [$fsdata];
                }else{
                  array_push($returndata[$fsdata["id"]][$fsdata["mountpoint"]], $fsdata);
                }
              }
  
              $resolve(array("status" => 0, "message" => "Successfully loaded data between {$data["from"]} and {$data["to"]}.", "data" => $returndata));
            })->otherwise(function(\Exception $e) use($resolve){
              $resolve($this->logging_api->getErrormessage("getFilesystemsHistory", "001", $e));
            });
          }else{
            $resolve($this->logging_api->getErrormessage("getFilesystemsHistory", "002"));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("getFilesystemsHistory", "003"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns a history about the nodes reported up/down status and services running stats.
     * Function made for: Web/App-Client
     * @param array $data   { "from" : [DateTime], "to" : [DateTime], "node_ids" : [array] }
     * @return array        Returns a status code array with the DBs stored values.
     */
    public function getNodeUPAndServicesHistory(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("from", $data) && array_key_exists("to", $data)){
          if(strtotime($data["from"]) &&  strtotime($data["to"]) && new \DateTime($data["from"]) < new \DateTime($data["to"])){
            $nodeids = "";
            if(array_key_exists("node_ids", $data) && is_array($data["node_ids"])) $nodeids = "AND nodeid IN (" . implode(",", $data["node_ids"]) . ")";
              $historical_data = [
                Promise\resolve((new DB_Api())->execute("SELECT nus.nodeid, n.hostname, nus.onlinestatus,  nus.firstreported AS node_firstreported, nus.lastreported AS node_lastreported
                                                          FROM nodes_up_status nus
                                                          JOIN nodes n ON n.id = nus.nodeid
                                                          WHERE ((nus.firstreported BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME))
                                                            OR (nus.firstreported <= CAST(? AS DATETIME) AND nus.lastreported >= (CAST(? AS DATETIME) - INTERVAL 1 MINUTE)))
                                                            {$nodeids}
                                                          ORDER BY nus.firstreported DESC", 
                                  array($data["from"], $data["to"], $data["from"], $data["to"]))),
                Promise\resolve((new DB_Api())->execute("SELECT nss.nodeid, n.hostname, nss.serviceid, nta.description, nss.servicestate, nss.firstreported AS service_firstreported, nss.lastreported AS service_lastreported
                                                          FROM nodes_services_status nss
                                                          JOIN nodes n ON n.id = nss.nodeid
                                                          JOIN nodetypes_avail nta ON nta.code = nss.serviceid
                                                          WHERE ((nss.firstreported BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME)) 
                                                            OR (nss.firstreported <= CAST(? AS DATETIME) AND nss.lastreported >= (CAST(? AS DATETIME) - INTERVAL 1 MINUTE)))
                                                            {$nodeids}
                                                          ORDER BY nss.firstreported DESC", 
                                    array($data["from"], $data["to"], $data["from"], $data["to"])))
              ];

              Promise\all($historical_data)->then(function($historical_data_returned) use(&$resolve, $data){
                $historicalNodeUPdata = $historical_data_returned[0]->resultRows;
                $historicalServicesdata = $historical_data_returned[1]->resultRows;

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
                
                $resolve(array("status" => 0, "message" => "Successfully loaded data between {$data["from"]} and {$data["to"]}.", "data" => $returndata));
              })->otherwise(function(\Exception $e) use($resolve){
                $resolve($this->logging_api->getErrormessage("getNodeUPAndServicesHistory", "001", $e));
              });
          }else{
            $resolve($this->logging_api->getErrormessage("getNodeUPAndServicesHistory", "002"));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("getNodeUPAndServicesHistory", "003"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
  }
