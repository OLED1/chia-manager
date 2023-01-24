<?php
  namespace ChiaMgmt\Chia_Infra_Sysinfo;
  use React\Promise;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Alerting\Alerting_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  use ChiaMgmt\Encryption\Encryption_Api;

  /**
   * The Chia_Infra_Sysinfo_Api class contains every needed methods to manage all available system specific performance data.
   * It stores and manages values regarding system load, ram, swap and filesystems.
   * This class is used by the client to send in data and from the webclient to get data.
   * The client can also be managed via this class.
   * @version 0.2
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Chia_Infra_Sysinfo_Api{
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
     * Holds an instance to the Alerting Class.
     * @var Alerting_Api
     */
    private $alerting_api;
        /**
     * Holds an instance to the Nodes Class.
     * @var Nodes_Api
     */
    private $nodes_api;
    /**
     * Holds an instance to the Encryption Class.
     * @var Encryption_Api
     */
    private $encryption_api;
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
      $this->alerting_api = new Alerting_Api();
      $this->nodes_api = new Nodes_Api();
      $this->encryption_api = new Encryption_Api();
      $this->server = $server;
    }

    /**
     * Update the available system information data of a certain node.
     * Function made for: Node Client
     * @throws Exception $e       Throws an exception on db errors.
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-Encryption-Encryption-Api.html#method_encryptString
     * @param  array  $data       {"system": {"load": {"1min": 3.61, "5min": 3.77, "15min": 3.13}, "memory": {"total": 8200859648, "free": 148439040, "buffers": 61440, "cached": 3128078336, "shared": 601456640}, "swap": {"total": 8199860224, "free": 2565165056}, "filesystem": [["/dev/sda1", "3.9G", "257M", "3.6G", "7%", "/dev/shm"]], "cpu": {"count": 4, "physical_cores": 4, "logical_cores": 4, "cores": " 2", "model": " Intel(R) Core(TM) i5-6300U CPU @ 2.40GHz"}, "uptime": 109064.54144072533}}
     * @param  array  $loginData  {"authhash": "[Querying Node's authhash]"}
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {"nodeid": [nodeid], "data": {[newly added harvester data]}}
     */
    public function updateSystemInfo(array $data, array $loginData = NULL): array
    {
      if(array_key_exists("system", $data)){
        try{
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
          $nodeid = $sql/*->fetchAll(\PDO::FETCH_ASSOC)*/[0]["id"];

          if(array_key_exists("load", $data["system"])){
            //Client version < 0.3, TODO Cleanup in some versions 
            $load_1_min = $data["system"]["load"]["1min"];
            $load_5_min = $data["system"]["load"]["5min"];
            $load_15_min = $data["system"]["load"]["15min"];
          }else if(array_key_exists("cpu", $data["system"]) && array_key_exists("load", $data["system"]["cpu"]) && array_key_exists("1min", $data["system"]["cpu"]["load"])){
            //Client version >=0.3
            $load_1_min = $data["system"]["cpu"]["load"]["1min"];
            $load_5_min = $data["system"]["cpu"]["load"]["5min"];
            $load_15_min = $data["system"]["cpu"]["load"]["15min"];
          }else{
            $load_1_min = 0;
            $load_5_min = 0;
            $load_15_min = 0;
          }

          //Windows does not return these values
          if(is_null($data["system"]["memory"]["buffers"])) $data["system"]["memory"]["buffers"] = 0;
          if(is_null($data["system"]["memory"]["cached"])) $data["system"]["memory"]["cached"] = 0;
          if(is_null($data["system"]["memory"]["shared"])) $data["system"]["memory"]["shared"] = 0;
          
          $sql = $this->db_api->execute("INSERT INTO chia_infra_sysinfo (id, nodeid, cpu_count, cpu_cores, cpu_model, os_type, os_name) VALUES(NULL, ?, ?, ?, ?, ?, ?)",
          array($nodeid, $data["system"]["cpu"]["physical_cores"], ($data["system"]["cpu"]["logical_cores"] / $data["system"]["cpu"]["physical_cores"]), $data["system"]["cpu"]["model"],
                $data["system"]["os"]["type"], implode(",", $data["system"]["os"]["name"])
              ));
          
          $last_insert_id = $this->db_api->lastInsertId();

          /**
           * Update all nodes system and services up / down status 
           */
          $this->setAllNodesSystemAndServicesUpStatus();
          
          /**
           * Insert CPU load data
           */
          $this_service_type_id = 5;
          $sql = $this->db_api->execute("INSERT INTO chia_infra_sysinfo_cpu_load (id, sysinfo_id, load_1_min, load_5_min, load_15_min) VALUES(NULL, ?, ?, ?, ?)", array($last_insert_id, $load_1_min, $load_5_min, $load_15_min));
          $service_insert_id = $this->db_api->lastInsertId();

          $updateData = [
            "node_id" => $nodeid,
            "service_insert_id" => $service_insert_id,
            "service_type_id" => $this_service_type_id,
            "defined_maximum" => ($data["system"]["cpu"]["physical_cores"] * 2),
            "current_service_level" => $load_15_min
          ];

          $this->updateAvailableServices($updateData);

          /**
           * Insert CPU usage data
           * Client version >= 0.3, TODO Cleanup in some versions 
           */
          $this_service_type_id = 6;
          if(array_key_exists("cpu", $data["system"]) && array_key_exists("usage", $data["system"]["cpu"])){
            $statement_string = "";
            $statement_data = [];
            $all_usages = 0;
            foreach($data["system"]["cpu"]["usage"] AS $arrkey => $thisusage){
              array_push($statement_data, $arrkey, $thisusage);
              $statement_string .= "(NULL, {$last_insert_id}, ?, ?)";
              if(array_key_exists($arrkey+1, $data["system"]["cpu"]["usage"])) $statement_string .= ",";
              $all_usages += $thisusage;
            }
  
            if(count($statement_data) > 0){
              $sql = $this->db_api->execute("INSERT INTO chia_infra_cpu_usage (id, sysinfo_id, cpu_number, cpu_usage) VALUES {$statement_string}", $statement_data);
              $service_insert_id = $this->db_api->lastInsertId();

              $updateData = [
                "node_id" => $nodeid,
                "service_insert_id" => $service_insert_id,
                "service_type_id" => $this_service_type_id,
                "defined_maximum" => 100,
                "current_service_level" => ($all_usages / count($data["system"]["cpu"]["usage"]))
              ];
              $this->updateAvailableServices($updateData);
            }
          }

          /**
           * Insert Memory usage data
           */
          $this_service_type_id = 7;
          $sql = $this->db_api->execute("INSERT INTO chia_infra_memory_usage (id, sysinfo_id, memory_total, memory_free, memory_buffers, memory_cached, memory_shared) VALUES(NULL, ?, ?, ?, ?, ?, ?)", 
                                          array($last_insert_id, $data["system"]["memory"]["total"], $data["system"]["memory"]["free"], $data["system"]["memory"]["buffers"], $data["system"]["memory"]["cached"], $data["system"]["memory"]["shared"]));

          $service_insert_id = $this->db_api->lastInsertId();
          $memoryused = $data["system"]["memory"]["total"] - $data["system"]["memory"]["free"] - ($data["system"]["memory"]["buffers"] + $data["system"]["memory"]["cached"]);

          $updateData = [
            "node_id" => $nodeid,
            "service_insert_id" => $service_insert_id,
            "service_type_id" => $this_service_type_id,
            "defined_maximum" => $data["system"]["memory"]["total"],
            "current_service_level" => $memoryused
          ];
          $this->updateAvailableServices($updateData);

          /**
           * Insert SWAP usage data
           */
          $this_service_type_id = 8;
          $sql = $this->db_api->execute("INSERT INTO chia_infra_swap_usage (id, sysinfo_id, swap_total, swap_free) VALUES(NULL, ?, ?, ?)", array($last_insert_id, $data["system"]["swap"]["total"], $data["system"]["swap"]["free"]));
          $service_insert_id = $this->db_api->lastInsertId();

          $updateData = [
            "node_id" => $nodeid,
            "service_insert_id" => $service_insert_id,
            "service_type_id" => $this_service_type_id,
            "defined_maximum" => $data["system"]["swap"]["total"],
            "current_service_level" => ($data["system"]["swap"]["total"] - $data["system"]["swap"]["free"])
          ];
          $this->updateAvailableServices($updateData);

          /**
           * Insert filesystem data
           */
          $statement_string = "";
          $statement_data = [];
          $this_service_type_id = 9;
          foreach($data["system"]["filesystem"] AS $arrkey => $thisfsdata){
            if(array_key_exists("device", $thisfsdata)) array_push($statement_data, addslashes($thisfsdata["device"]), $thisfsdata["total"], $thisfsdata["used"], $thisfsdata["free"], addslashes($thisfsdata["mountpoint"])); //Client version >=0.3
            else array_push($statement_data, $thisfsdata[0], $thisfsdata[1], $thisfsdata[2], $thisfsdata[3], $thisfsdata[5]);  //Client version < 0.3, TODO Cleanup in some versions
            $statement_string .= "(NULL, {$last_insert_id}, ?, ?, ?, ?, ?)";
            if(array_key_exists($arrkey+1, $data["system"]["filesystem"])) $statement_string .= ",";
          }

          if(count($statement_data) > 0){
            $sql = $this->db_api->execute("INSERT INTO chia_infra_sysinfo_filesystems (id, sysinfo_id, device, size, used, avail, mountpoint) VALUES {$statement_string}", $statement_data);
          }

          $sql = $this->db_api->execute("SELECT id, mountpoint, size, used FROM chia_infra_sysinfo_filesystems WHERE sysinfo_id = ?", array($last_insert_id));
          foreach($sql/*->fetchAll(\PDO::FETCH_ASSOC)*/ AS $arrkey => $curr_fs_data){
            $updateData = [
              "node_id" => $nodeid,
              "service_insert_id" => $curr_fs_data["id"],
              "service_type_id" => $this_service_type_id,
              "service_target" => $curr_fs_data["mountpoint"],
              "defined_maximum" => $curr_fs_data["size"],
              "current_service_level" => $curr_fs_data["used"]
            ];
            $this->updateAvailableServices($updateData);
          }

          $this->alerting_api->alertAllFoundWARNandCRIT();

          return array("status" => 0, "message" => "Successfully updated system information for node $nodeid.", "data" => ["nodeid" => $nodeid]);
        }catch(\Exception $e){
          print_r($e);
          return $this->logging_api->getErrormessage("001", $e);
        }
      }
    }

    /**
     * Returns an array of all available on the database stored system information values.
     * Function made for: Web GUI/App
     * @throws Exception $e                 Throws an exception on db errors.
     * @param  array $data                  { NULL } Will be changed to { nodeid: [NULL|nodeid] } as soon as the method needs to be called outsite of the web gui.
     * @param  array $loginData             { NULL } No logindata will be needed to be able to return valid data.
     * @param  ChiaWebSocketServer $server  An instance to websocket server class to be able to send data directly to nodes.
     * @param  int $nodeid                  The node id to get only node specific data. Can be NULL if all data will be queried. Will be deprecated as soon as the method needs to be called outsite of the web gui.
     * @return array                        {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": [Found system information data array]}
     */
    public function getSystemInfo(array $data = NULL, array $loginData = NULL, $server = NULL, int $nodeid = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData, $server, $nodeid){
        $statement_string = "WHERE n.id = (
          SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
        )";
        $statement_array = [];
        if(!is_null($data) && array_key_exists("nodeid", $data) && is_numeric($data["nodeid"])){
          $statement_string = "WHERE n.id = ?";
          array_push($statement_array, $data["nodeid"]);
        }

        $systemInfos = Promise\resolve((new DB_Api())->execute("SELECT n.id, cias.id AS service_id, n.hostname, n.nodeauthhash, cias.service_type,
                                                                        cis.os_type, cis.os_name,
                                                                        cis.cpu_count, cis.cpu_cores, cis.cpu_model,
                                                                        ciscl.load_1_min,ciscl.load_5_min ,ciscl.load_15_min,
                                                                        cicu.cpu_number, cicu.cpu_usage,
                                                                        cimu.memory_total, cimu.memory_free, cimu.memory_buffers, cimu.memory_shared, cimu.memory_cached,
                                                                        cisu.swap_total, cisu.swap_free,
                                                                        cisf.device, cisf.size, cisf.used, cisf.avail, cisf.mountpoint,
                                                                        cias.curr_service_insert_id, cias.service_state, cias.time_or_usage, cias.service_state_first_reported, cias.service_state_last_reported,
                                                                        cist.service_desc, ar.monitor,
                                                                        (CASE WHEN ad.downtime_comment IS NOT NULL THEN 1
                                                                            ELSE 0
                                                                        END) AS downtime_active
                                                                FROM nodes n
                                                                LEFT JOIN chia_infra_available_services cias ON cias.id = (SELECT cias1.id
                                                                                                                            FROM chia_infra_available_services cias1
                                                                                                                            WHERE cias1.service_target = cias.service_target AND cias1.service_type = cias.service_type AND cias1.node_id = n.id       
                                                                                                                            ORDER BY cias1.service_state_first_reported DESC
                                                                                                                            LIMIT 1)
                                                                LEFT JOIN chia_infra_sysinfo cis ON (cias.service_type = 5 OR cias.service_type = 6) AND cis.id = (SELECT cis1.id 
                                                                                                              FROM chia_infra_sysinfo cis1 
                                                                                                              WHERE cis1.nodeid = n.id
                                                                                                              ORDER BY cis1.timestamp DESC
                                                                                                              LIMIT 1)
                                                                LEFT JOIN chia_infra_sysinfo_cpu_load ciscl ON ciscl.id = cias.curr_service_insert_id AND cias.service_type = 5
                                                                LEFT JOIN chia_infra_cpu_usage cicu ON cias.service_type = 6 AND cicu.sysinfo_id = ( SELECT sysinfo_id FROM chia_infra_cpu_usage WHERE id = cias.curr_service_insert_id )
                                                                LEFT JOIN chia_infra_memory_usage cimu ON cimu.id = cias.curr_service_insert_id AND cias.service_type = 7
                                                                LEFT JOIN chia_infra_swap_usage cisu ON cisu.id = cias.curr_service_insert_id AND cias.service_type = 8
                                                                LEFT JOIN chia_infra_sysinfo_filesystems cisf ON cisf.id = cias.curr_service_insert_id AND cias.service_type = 9
                                                                LEFT JOIN alerting_rules ar on ar.id = cias.refers_to_rule_id
                                                                LEFT JOIN alerting_downtimes ad ON ad.node_id = n.id AND (ad.downtime_type = 0 OR (ad.downtime_type = 1 AND ad.downtime_service_type = cias.service_type AND ad.downtime_service_target = cias.service_target)) AND NOW() BETWEEN ad.downtime_from AND ad.downtime_to
                                                                LEFT JOIN chia_infra_service_types cist ON cist.id = cias.service_type
                                                                $statement_string AND ar.monitor = 1
                                                                ORDER BY n.id ASC, cias.service_type ASC, cisf.mountpoint ASC", 
                                          $statement_array));

        $systemInfos->then(function($systemInfos_returned) use(&$resolve){
          $returnarray = [];
          foreach($systemInfos_returned->resultRows AS $arrkey => $sysinfodata){
            $data_current = (strtotime("now") - strtotime($sysinfodata["service_state_last_reported"]) <= 120 ? true : false );


            if(!array_key_exists($sysinfodata["id"], $returnarray)) $returnarray[$sysinfodata["id"]] = [];
            if($sysinfodata["service_type"] == 1){
              $returnarray[$sysinfodata["id"]]["node"] = [
                "service_id" => $sysinfodata["service_id"],
                "service_type" => $sysinfodata["service_type"],
                "hostname" => $sysinfodata["hostname"],
                "nodeauthhash" => $this->encryption_api->decryptString($sysinfodata["nodeauthhash"]),
                "upstatus" => $sysinfodata["service_state"],
                "status_since" => $sysinfodata["time_or_usage"],
                "monitor_service" => $sysinfodata["monitor"],
                "downtime_active" => $sysinfodata["downtime_active"],
                "state_first_reported" => $sysinfodata["service_state_first_reported"],
                "state_last_reported" => $sysinfodata["service_state_last_reported"],
                "data_current" => $data_current,
                "service_desc" => $sysinfodata["service_desc"]
              ];
            }else if(in_array($sysinfodata["service_type"], [2,3,4])){
              $node_types = [2 => "farmer", 3 => "harvester", 4 => "wallet"];
              $returnarray[$sysinfodata["id"]][$node_types[$sysinfodata["service_type"]]] = [
                "service_id" => $sysinfodata["service_id"],
                "service_type" => $sysinfodata["service_type"],
                "service_state" => $sysinfodata["service_state"],
                "status_since" => $sysinfodata["time_or_usage"],
                "monitor_service" => $sysinfodata["monitor"],
                "downtime_active" => $sysinfodata["downtime_active"],
                "state_first_reported" => $sysinfodata["service_state_first_reported"],
                "state_last_reported" => $sysinfodata["service_state_last_reported"],
                "data_current" => $data_current,
                "service_desc" => $sysinfodata["service_desc"]
              ];
            }else if($sysinfodata["service_type"] == 5){
              if(!array_key_exists("cpu", $returnarray[$sysinfodata["id"]])) $returnarray[$sysinfodata["id"]]["cpu"] = [];
              $returnarray[$sysinfodata["id"]]["cpu"]["load"] = [
                "service_id" => $sysinfodata["service_id"],
                "service_type" => $sysinfodata["service_type"],
                "load_1_min" => $sysinfodata["load_1_min"],
                "load_5_min" => $sysinfodata["load_5_min"],
                "load_15_min" => $sysinfodata["load_15_min"],
                "usage_15_min" => $sysinfodata["time_or_usage"],
                "service_state" => $sysinfodata["service_state"],
                "monitor_service" => $sysinfodata["monitor"],
                "downtime_active" => $sysinfodata["downtime_active"],
                "state_first_reported" => $sysinfodata["service_state_first_reported"],
                "state_last_reported" => $sysinfodata["service_state_last_reported"],
                "data_current" => $data_current,
                "service_desc" => $sysinfodata["service_desc"]
              ];
            }else if($sysinfodata["service_type"] == 6){
              if(!array_key_exists("cpu", $returnarray[$sysinfodata["id"]])) $returnarray[$sysinfodata["id"]]["cpu"] = [];
              if(!array_key_exists("usage", $returnarray[$sysinfodata["id"]]["cpu"]) || !array_key_exists("overall", $returnarray[$sysinfodata["id"]]["cpu"]["usage"])){
                $returnarray[$sysinfodata["id"]]["cpu"]["usage"]["overall"] = [
                  "service_id" => $sysinfodata["service_id"],
                  "service_type" => $sysinfodata["service_type"],
                  "service_target" => "None",
                  "total_usage" => $sysinfodata["time_or_usage"],
                  "service_state" => $sysinfodata["service_state"],
                  "monitor_service" => $sysinfodata["monitor"],
                  "downtime_active" => $sysinfodata["downtime_active"],
                  "state_first_reported" => $sysinfodata["service_state_first_reported"],
                  "state_last_reported" => $sysinfodata["service_state_last_reported"],
                  "data_current" => $data_current,
                  "service_desc" => $sysinfodata["service_desc"]
                ];
              }
              $returnarray[$sysinfodata["id"]]["cpu"]["usage"]["usages"][$sysinfodata["cpu_number"]] = $sysinfodata["cpu_usage"];
            }else if($sysinfodata["service_type"] == 7){
              $returnarray[$sysinfodata["id"]]["memory"]["ram"] = [
                "service_id" => $sysinfodata["service_id"],
                "service_type" => $sysinfodata["service_type"],
                "memory_total" => $sysinfodata["memory_total"],
                "memory_free" => $sysinfodata["memory_free"],
                "memory_buffers" => $sysinfodata["memory_buffers"],
                "memory_shared" => $sysinfodata["memory_shared"],
                "memory_cached" => $sysinfodata["memory_cached"],
                "service_status" => $sysinfodata["service_state"],
                "total_usage" => $sysinfodata["time_or_usage"],
                "monitor_service" => $sysinfodata["monitor"],
                "downtime_active" => $sysinfodata["downtime_active"],
                "state_first_reported" => $sysinfodata["service_state_first_reported"],
                "state_last_reported" => $sysinfodata["service_state_last_reported"],
                "data_current" => $data_current,
                "service_desc" => $sysinfodata["service_desc"]
              ];
            }else if($sysinfodata["service_type"] == 8){
              $returnarray[$sysinfodata["id"]]["memory"]["swap"] = [
                "service_id" => $sysinfodata["service_id"],
                "service_type" => $sysinfodata["service_type"],
                "swap_total" => $sysinfodata["swap_total"],
                "swap_free" => $sysinfodata["swap_free"],
                "service_status" => $sysinfodata["service_state"],
                "total_usage" => $sysinfodata["time_or_usage"],
                "monitor_service" => $sysinfodata["monitor"],
                "downtime_active" => $sysinfodata["downtime_active"],
                "state_first_reported" => $sysinfodata["service_state_first_reported"],
                "state_last_reported" => $sysinfodata["service_state_last_reported"],
                "data_current" => $data_current,
                "service_desc" => $sysinfodata["service_desc"]
              ];
            }else if($sysinfodata["service_type"] == 9){
              $returnarray[$sysinfodata["id"]]["filesystems"][$sysinfodata["mountpoint"]] = [
                "service_id" => $sysinfodata["service_id"],
                "service_type" => $sysinfodata["service_type"],
                "device" => html_entity_decode(stripslashes($sysinfodata["device"]),ENT_QUOTES,'UTF-8'),
                "mountpoint" => html_entity_decode(stripslashes($sysinfodata["mountpoint"]),ENT_QUOTES,'UTF-8'),
                "size" => $sysinfodata["size"],
                "used" => $sysinfodata["used"],
                "avail" => $sysinfodata["avail"],
                "service_status" => $sysinfodata["service_state"],
                "total_usage" => $sysinfodata["time_or_usage"],
                "monitor_service" => $sysinfodata["monitor"],
                "downtime_active" => $sysinfodata["downtime_active"],
                "state_first_reported" => $sysinfodata["service_state_first_reported"],
                "state_last_reported" => $sysinfodata["service_state_last_reported"],
                "data_current" => $data_current,
                "service_desc" => $sysinfodata["service_desc"]
              ];
            }
            if(($sysinfodata["service_type"] == 5 || $sysinfodata["service_type"] == 6) && 
              (!array_key_exists("os", $returnarray[$sysinfodata["id"]]) || !array_key_exists("info", $returnarray[$sysinfodata["id"]]["cpu"]))){
                $returnarray[$sysinfodata["id"]]["os"] = [
                  "os_type" => $sysinfodata["os_type"],
                  "os_name" => $sysinfodata["os_name"]
                ];

                $returnarray[$sysinfodata["id"]]["cpu"]["info"] = [
                  "cpu_count" => $sysinfodata["cpu_count"],
                  "cpu_cores" => $sysinfodata["cpu_cores"],
                  "cpu_model" => $sysinfodata["cpu_model"]
                ];
            }
          }

          $resolve(array("status" => 0, "message" => "Successfully loaded latest system information.", "data" => $returnarray));
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getSystemInfo", "001", $e));
        });    
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Informs the node client to query new system information data.
     * @throws Exception $e                   Throws an exception on db errors.
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-WebSocketServer-ChiaWebSocketServer.html#method_messageSpecificNode
     * @param  array $data                    { authhash: [Target Node Authhash] }
     * @param  array $loginData               { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server    An instance to the websocket server to be able to send data to the connected clients.
     * @return array                          Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}} from the subfunction calls.
     */
    public function querySystemInfo(array $data = NULL, array $loginData = NULL, $server = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData, $server){
        $querydata = [];
        $querydata["data"]["querySystemInfo"] = array(
          "status" => 0,
          "message" => "Query systeminfo data.",
          "data"=> array()
        );

        $callfunction = "messageAllNodes";
        if(array_key_exists("nodeinfo", $data) && array_key_exists("authhash", $data["nodeinfo"])){
          $querydata["nodeinfo"]["authhash"] = $data["nodeinfo"]["authhash"];
          $callfunction = "messageSpecificNode";
        }

        if(!is_null($server)){
          $function_call = Promise\resolve($server->$callfunction($querydata));
        }else{
          $function_call = Promise\resolve((new WebSocket_Api())->sendToWSS($callfunction, $querydata));
        }

        $function_call->then(function($function_call_returned) use(&$resolve){
          $resolve($function_call_returned);
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns all or node specific available services.
     *
     * @param array $data
     * @return array
     */
    public function getAvailableServices(array $data = []): object
    {     
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        $where_statement = "";
        $statement_array = [];
        if(array_key_exists("node_id", $data)){
          $where_statement .= "cias.node_id = ? AND ";
          array_push($statement_array, $data["node_id"]);
        }

        if(array_key_exists("service_type_id", $data)){
          $where_statement .= "cias.service_type = ? AND ";
          array_push($statement_array, $data["service_type_id"]);
        }

        if(array_key_exists("service_target", $data)){
          if(is_null($data["service_target"]) || empty($data["service_target"])){
            $where_statement .= "(cias.service_target IS NULL OR cias.service_target = '') AND ";
          }else{
            $where_statement .= "cias.service_target = ? AND ";
            array_push($statement_array, $data["service_target"]);
          }
        }

        /*$available_services = Promise\resolve((new DB_Api())->execute("SELECT cias.id, cias.curr_service_insert_id, cias.service_target ,cias.service_type, cias.refers_to_rule_id, ar.rule_target, ar.warn_at_after, ar.crit_at_after, cias.service_state, cias.time_or_usage, cias.node_id, ar.monitor, cias.service_state_first_reported, cias.service_state_last_reported
                                                                        FROM chia_infra_available_services cias 
                                                                        JOIN alerting_rules ar ON ar.id = cias.refers_to_rule_id
                                                                        WHERE $where_statement cias.service_state_first_reported = (SELECT max(cias1.service_state_first_reported) FROM chia_infra_available_services cias1 WHERE cias1.node_id = cias.node_id AND cias1.service_type = cias.service_type AND cias1.service_target = cias.service_target)", $statement_array));*/
                
        $available_services = Promise\resolve((new DB_Api())->execute("SELECT cias.id, cias.curr_service_insert_id, cias.service_target ,cias.service_type, cias.refers_to_rule_id, ar.rule_target, ar.warn_at_after, ar.crit_at_after, cias.service_state, cias.time_or_usage, cias.node_id, ar.monitor, cias.service_state_first_reported, cias.service_state_last_reported
                                                                        FROM chia_infra_available_services cias 
                                                                        JOIN alerting_rules ar ON ar.id = cias.refers_to_rule_id
                                                                        WHERE $where_statement current = 1", $statement_array));

        
        $available_services->then(function($available_services_returned) use(&$resolve){
          $available_services_returned = $available_services_returned->resultRows;

          $found_data = [
            "by-avail-serv-id" => [],
            "by-avail-serv-target" => []
          ];
          foreach($available_services_returned AS $arrkey => $found_service){
            if(!array_key_exists($found_service["node_id"], $found_data["by-avail-serv-id"])) $found_data["by-avail-serv-id"][$found_service["node_id"]] = [];
            if(!array_key_exists($found_service["service_type"], $found_data["by-avail-serv-id"][$found_service["node_id"]])) $found_data["by-avail-serv-id"][$found_service["node_id"]][$found_service["service_type"]] = [];
            $found_data["by-avail-serv-id"][$found_service["node_id"]][$found_service["service_type"]][$found_service["id"]] = $found_service;
            
            $rule_node_target = (is_null($found_service["rule_target"]) ? "All" : $found_service["rule_target"]);
            if(!array_key_exists($found_service["node_id"], $found_data["by-avail-serv-target"])) $found_data["by-avail-serv-target"][$found_service["node_id"]] = [];
            if(!array_key_exists($found_service["service_type"], $found_data["by-avail-serv-target"][$found_service["node_id"]])) $found_data["by-avail-serv-target"][$found_service["node_id"]][$found_service["service_type"]] = [];
            if(!array_key_exists($rule_node_target, $found_data["by-avail-serv-target"][$found_service["node_id"]][$found_service["service_type"]])) $found_data["by-avail-serv-target"][$found_service["node_id"]][$found_service["service_type"]][$rule_node_target] = [];
            $found_data["by-avail-serv-target"][$found_service["node_id"]][$found_service["service_type"]][$rule_node_target][$found_service["id"]] = $found_service;
          }

          $resolve(array("status" => 0, "message" => "Successfully loaded available services.", "data" => $found_data));
        })->otherwise(function(\Exception $e) use(&$resolve){
          //TODO Implement correct status codes
          print_r($e);
          $resolve(array("status" => 1, "message" => "An error occured {$e->getMessage()}."));
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Inserts a new available sysinfo service or updates an existing one.
     *
     * @param array $data
     * @return array
     */
    public function updateAvailableServices(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        echo "updateAvailableServices\n";
        print_r($data);
        
        if(array_key_exists("node_id", $data) && array_key_exists("service_type_id", $data) && array_key_exists("service_insert_id", $data) && 
        array_key_exists("defined_maximum", $data) && array_key_exists("current_service_level", $data) && 
        ($data["service_type_id"] == 9 && array_key_exists("service_target", $data)) || $data["service_type_id"] < 9){
                    
          $nodeid = $data["node_id"];
          $this_service_type_id = $data["service_type_id"];
          $this_service_target = (array_key_exists("service_target", $data) && !is_null($data["service_target"]) ? $data["service_target"] : NULL);
          
          $this_service_alerting_infos = Promise\resolve($this->alerting_api->getRuleInformationOfService(["service_type_id" => $this_service_type_id, "node_id" => $nodeid, "service_target" => $this_service_target]));
          $this_service_alerting_infos->then(function($this_service_alerting_infos_returned) use($data, $nodeid, $this_service_type_id, $this_service_target){
            $this_service_alerting_infos_returned = $this_service_alerting_infos_returned["data"];

            $service_insert_id = $data["service_insert_id"];
            $defined_maximum = $data["defined_maximum"];
            $current_service_level = $data["current_service_level"];
            $perc_or_min_value = $this_service_alerting_infos_returned["perc_or_min_value"];
            $warn_level_at_after = $this_service_alerting_infos_returned["warn_at_after"];
            $crit_level_at_after = $this_service_alerting_infos_returned["crit_at_after"];
            $current_service_minutes = (array_key_exists("current_service_minutes", $data) && !is_null($data["current_service_minutes"]) ? $data["current_service_minutes"] : NULL);
            
            $promises = [
              Promise\resolve($this->getAvailableServices(["node_id" => $nodeid, "service_type_id" => $this_service_type_id, "service_target" => $this_service_target])),
              Promise\resolve($this->alerting_api->calculateAlertingLevel(["defined_maximum" => $defined_maximum, "current_service_level" => $current_service_level, "perc_or_min_value" => $perc_or_min_value, "warn_level_at_after" => $warn_level_at_after, "crit_level_at_after" => $crit_level_at_after, "current_service_minutes" => $current_service_minutes])),
              Promise\resolve((new DB_API())->execute("UPDATE chia_infra_available_services SET current = 0 WHERE current = 1 AND (service_target = ? OR service_target IS NULL OR service_target = '') AND service_type = ? AND node_id = ?", array($this_service_target, $this_service_type_id, $nodeid)))
            ];
            
            Promise\all($promises)->then(function($all_returned) use(&$resolve, $nodeid, $this_service_type_id, $this_service_target, $this_service_alerting_infos_returned, $service_insert_id){                          
              echo "NODEID: {$nodeid} SERVICE_TYPE_ID: {$this_service_type_id}\n";
              print_r($all_returned[1]);
              
              $found_node_available_services = [];
              if(array_key_exists("data", $all_returned[0])){
                $found_node_available_services = $all_returned[0]["data"];
              }

              $current_service_alerting_level = [];
              if(array_key_exists("data", $all_returned[1])){
                $current_service_alerting_level = $all_returned[1]["data"];
              }

              $insert_new = false;
              if(array_key_exists($nodeid, $found_node_available_services["by-avail-serv-id"]) &&
                array_key_exists($this_service_type_id ,$found_node_available_services["by-avail-serv-id"][$nodeid]) && 
                count($found_node_available_services["by-avail-serv-id"][$nodeid][$this_service_type_id]) > 0)
              {
                $found_avail_service = $found_node_available_services["by-avail-serv-id"][$nodeid][$this_service_type_id];
                $target_avail_serv_id = array_key_first($found_avail_service);
                $target_avail_service = $found_avail_service[$target_avail_serv_id];
                
                $set_current = ", current = 1";
                if($target_avail_service["service_state"] != $current_service_alerting_level["level"]){
                  $insert_new = true;
                  $set_current = "";
                }
               
                $update_service = Promise\resolve((new DB_Api())->execute("UPDATE chia_infra_available_services SET curr_service_insert_id = ?, time_or_usage = ?{$set_current} , service_state_last_reported = NOW() WHERE id = ?",
                                                  array($service_insert_id, intval($current_service_alerting_level["time_or_usage"]), $target_avail_serv_id)));

                $update_service->otherwise(function(\Exception $e) use(&$resolve){
                  //TODO Implement correct status code
                  return $resolve(array("status" => 1, "messages" => "An error occured {$e->getMessage()}."));
                });
              }else{
                $insert_new = true;
              }

              if($insert_new){
                echo "\nINSERT INTO chia_infra_available_services (id, curr_service_insert_id, service_target, service_type, refers_to_rule_id, service_state, time_or_usage, node_id, current, service_state_first_reported, service_state_last_reported) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())\n";
                print_r(array($service_insert_id, $this_service_target, $this_service_type_id, $this_service_alerting_infos_returned["id"], $current_service_alerting_level["level"], $current_service_alerting_level["time_or_usage"], $nodeid));
                
                $insert_new = Promise\resolve((new DB_Api())->execute("INSERT INTO chia_infra_available_services (id, curr_service_insert_id, service_target, service_type, refers_to_rule_id, service_state, time_or_usage, node_id, current, service_state_first_reported, service_state_last_reported) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())", 
                                              array($service_insert_id, $this_service_target, $this_service_type_id, $this_service_alerting_infos_returned["id"], $current_service_alerting_level["level"], $current_service_alerting_level["time_or_usage"], $nodeid)));
                                              
                $insert_new->otherwise(function(\Exception $e) use(&$resolve){
                  //TODO Implement correct status code
                  print_r($e);
                  return $resolve(array("status" => 1, "messages" => "An error occured {$e->getMessage()}."));
                });
              }

              $resolve(array("status" => 0, "message" => "Successfully updated available services with new information."));
            });
          });

        }else{
          //TODO Implement correct status code
          $resolve(array("status" => 1, "messages" => "Not all data stated."));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Determines all nodes and it's services up and down status and updates the available services table automatically.
     *
     * @return array
     */
    public function setAllNodesSystemAndServicesUpStatus(array $data = []): object
    {     
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("nodeid", $data)){
          $available_nodes = Promise\resolve($this->nodes_api->getCurrentChiaNodesUPAndServiceStatus($data));
        }else{
          $available_nodes = Promise\resolve($this->nodes_api->getCurrentChiaNodesUPAndServiceStatus());
        }

        $available_nodes->then(function($available_nodes_returned) use(&$resolve, $data){
          if(array_key_exists("data", $available_nodes_returned)) $available_nodes_returned = $available_nodes_returned["data"];

          echo "AVAIL NODES RETURNED:\n";
          print_r($available_nodes_returned);

          foreach($available_nodes_returned AS $nodeid => $services_data){
            echo "\n--------------------\n";
            echo "LOOP:\n";
            echo "\n-------------------\n";

            $this_service_type_id = 1;
            $node_up_down_since = (strtotime($services_data["onlinestatus"]["node_lastreported"]) - strtotime($services_data["onlinestatus"]["node_firstreported"])) / 60;
            $updateData = [
              "node_id" => $nodeid,
              "service_insert_id" => $services_data["onlinestatus"]["entry_id"],
              "service_type_id" => $this_service_type_id,
              "service_target" => NULL,
              "defined_maximum" => NULL,
              "current_service_level" => $services_data["onlinestatus"]["status"],
              "current_service_minutes" => $node_up_down_since
            ];

            //Promise\resolve($this->updateAvailableServices($updateData));

            echo "Services Data: \n";
            print_r($services_data["services"]);

            foreach($services_data["services"] AS $service_id => $node_service_state){
              $service_up_down_since = (strtotime($node_service_state["service_lastreported"]) - strtotime($node_service_state["service_firstreported"])) / 60;
              
              $updateData = [
                "node_id" => $nodeid,
                "service_insert_id" => $node_service_state["entry_id"],
                "service_type_id" => ($service_id - 1),
                "service_target" => NULL,
                "defined_maximum" => NULL,
                "current_service_level" => $node_service_state["servicestate"],
                "current_service_minutes" => $service_up_down_since
              ];

              echo "=========================================\n";
              echo "setAllNodesSystemAndServicesUpStatus: \n";
              print_r($updateData);
              echo "=========================================\n";
    
              Promise\resolve($this->updateAvailableServices($updateData));
            }
          }

          $resolve(array("status" => 0, "message" => "Successfully set all nodes system and service status."));
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Sets the status of a certain service to monitored or unmonitored.
     *
     * @param array $data
     * @return array
     */
    public function editMonitoredServices(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("node_id", $data) && is_numeric($data["node_id"]) && $data["node_id"] > 2 && 
          array_key_exists("service_id", $data) && is_numeric($data["service_id"]) &&
          array_key_exists("monitor", $data) && is_bool($data["monitor"])
        ){
          $available_services = Promise\resolve((new DB_Api())->execute("SELECT cias.id AS service_id, cias.service_type, ar.id AS alerting_id, 
                                                                          (CASE WHEN (cias.service_target IS NULL OR cias.service_target = '') AND cist.perc_or_min= 1 THEN 'total downtime'
                                                                                WHEN (cias.service_target IS NULL OR cias.service_target = '') AND cist.perc_or_min = 0 THEN 'total usage'
                                                                                ELSE cias.service_target
                                                                          END) AS service_target, 
                                                                          (CASE WHEN ar.warn_at_after = -1 AND ar.crit_at_after = -1 AND ar.rule_default = 0 THEN 0 #Revert entry to default rule
                                                                                WHEN (ar.warn_at_after > -1 OR ar.crit_at_after > -1) AND ar.rule_default = 0 THEN 1 #Update found rule to monitor = 1
                                                                                WHEN ar.rule_default = 1 THEN 2 #Do nothing (it's a default rule)
                                                                                ELSE 2 #Do nothing (it's a default rule)
                                                                          END) AS update_replace_delete, ar.rule_default, 
                                                                          (SELECT id from alerting_rules WHERE system_target = 1 AND rule_type = cias.service_type AND rule_default = 1) AS default_rule_id, ar.monitor 
                                                                        FROM chia_infra_available_services cias
                                                                        JOIN alerting_rules ar ON ar.id = cias.refers_to_rule_id 
                                                                        JOIN chia_infra_service_types cist on cist.id = cias.service_type
                                                                        WHERE cias.id = ? AND cias.node_id = ?", 
                                                                        array($data["service_id"], $data["node_id"])));

          $available_services->then(function($available_services_returned) use(&$resolve, $data){
            $found_service = $available_services_returned->resultRows;

            if(array_key_exists(0, $found_service) && array_key_exists("rule_default", $found_service[0])){
              $found_service = $found_service[0];
              $default_rule = boolval($found_service["rule_default"]);
              $service_type = $found_service["service_type"];
              $rule_target = $found_service["service_target"];

              if($default_rule){
                $statements_to_resolve = [
                  Promise\resolve($this->alerting_api->addCustomRule(["nodeid" => $data["node_id"], "service_type" => $service_type, "monitor" => boolval($data["monitor"]), "service_name" => $rule_target, "warn_at_after" => -1, "crit_at_after" => -1]))
                ];
              }else{
                $statements_to_resolve = [];
                if($found_service["monitor"] == 0){
                  if($found_service["update_replace_delete"] == 0){ //0 = Revert to default rule, which is per default an enabled monitoring rule
                    $statements_to_resolve = [
                      Promise\resolve((new DB_Api())->execute("UPDATE chia_infra_available_services SET refers_to_rule_id = ? WHERE id = ?", array($found_service["default_rule_id"], $found_service["service_id"]))),
                      Promise\resolve((new DB_Api())->execute("DELETE FROM alerting_rules WHERE id = ?", array($found_service["alerting_id"])))
                    ];
                  }else if($found_service["update_replace_delete"] == 1){ // 1 = Disable the existing service by disable it's custom rule.
                    $statements_to_resolve = [
                      Promise\resolve((new DB_Api())->execute("UPDATE alerting_rules SET monitor = 1 WHERE id = ?", array($found_service["alerting_id"])))
                    ];
                  }else{
                    //TODO Implement correct status code
                    return $resolve(array("status" => 1, "message" => "A default rule cannot be edited."));
                  }
                }else if($found_service["monitor"] == 1){
                  $statements_to_resolve = [
                    Promise\resolve((new DB_Api())->execute("UPDATE alerting_rules SET monitor = 0 WHERE id = ?", $found_service["alerting_id"]))
                  ];
                }else{
                  //TODO Implement correct status code
                  return $resolve(array("status" => 1, "message" => "Monitor value '{$found_service["monitor"]}' not valid."));
                }                
              }

              Promise\all($statements_to_resolve)->then(function($all_returned) use(&$resolve, $data){
                if(array_key_exists("status", $all_returned) && $all_returned["status"] != 0) return $resolve($all_returned[0]);
                
                $new_monitored_services = Promise\resolve($this->alerting_api->getConfigurableDowntimeServices(["node_id" => $data["node_id"]]));
                $new_monitored_services->then(function($new_monitored_services_returned) use(&$resolve, $data){
                  if(array_key_exists("data", $new_monitored_services_returned)) $new_monitored_services = $new_monitored_services_returned["data"];
                  else $new_monitored_services = [];
      
                  $resolve(array("status" => 0, "message" => "Successfully set monitor to {$data["monitor"]} for service with ID {$data["service_id"]}.", "data" => $new_monitored_services));  
                });
              })->otherwise(function(\Exception $e) use(&$resolve){
                //TODO Implement correct status code
                return $resolve(array("status" => 1, "message" => "An error occured {$e->getMessage()}."));
              });
            }else{
              //TODO Implement correct status code
              $resolve(array("status" => 1, "message" => "This service has no valid alerting rule. Please report this error to the dev team."));
            }
          })->otherwise(function(\Exception $e) use(&$resolve){
            //TODO Implement correct status code
            $resolve(array("status" => 1, "message" => "An error occured {$e->getMessage()}."));
          });
        }else{
          //TODO Implement correct status code
          $resolve(array("status" => 1, "message" => "Not all data stated."));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
  }
?>
