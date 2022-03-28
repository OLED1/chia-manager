<?php
  namespace ChiaMgmt\Chia_Infra_Sysinfo;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Alerting\Alerting_Api;
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
          $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

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
          
          $sql = $this->db_api->execute("INSERT INTO chia_infra_sysinfo (id, nodeid, load_1min, load_5min, load_15min, memory_total, memory_free, memory_buffers, memory_cached, memory_shared, swap_total, swap_free, cpu_count, cpu_cores, cpu_model, os_type, os_name) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
          array($nodeid, $load_1_min, $load_5_min, $load_15_min,
                $data["system"]["memory"]["total"], $data["system"]["memory"]["free"], $data["system"]["memory"]["buffers"], $data["system"]["memory"]["cached"], $data["system"]["memory"]["shared"],
                $data["system"]["swap"]["total"], $data["system"]["swap"]["free"],
                $data["system"]["cpu"]["physical_cores"], ($data["system"]["cpu"]["logical_cores"] / $data["system"]["cpu"]["physical_cores"]), $data["system"]["cpu"]["model"],
                $data["system"]["os"]["type"], $data["system"]["os"]["name"]
              ));
          
          $last_insert_id = $this->db_api->lastInsertId();
          
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
            if(array_key_exists("device", $thisfsdata)) array_push($statement_data, $thisfsdata["device"], $thisfsdata["total"], $thisfsdata["used"], $thisfsdata["free"], $thisfsdata["mountpoint"]); //Client version >=0.3
            else array_push($statement_data, $thisfsdata[0], $thisfsdata[1], $thisfsdata[2], $thisfsdata[3], $thisfsdata[5]);  //Client version < 0.3, TODO Cleanup in some versions
            $statement_string .= "(NULL, {$last_insert_id}, ?, ?, ?, ?, ?)";
            if(array_key_exists($arrkey+1, $data["system"]["filesystem"])) $statement_string .= ",";
          }

          if(count($statement_data) > 0){
            $sql = $this->db_api->execute("INSERT INTO chia_infra_sysinfo_filesystems (id, sysinfo_id, device, size, used, avail, mountpoint) VALUES {$statement_string}", $statement_data);
          }

          $sql = $this->db_api->execute("SELECT id, mountpoint, size, used FROM chia_infra_sysinfo_filesystems WHERE sysinfo_id = ?", array($last_insert_id));
          foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $curr_fs_data){
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
    public function getSystemInfo(array $data = NULL, array $loginData = NULL, $server = NULL, int $nodeid = NULL): array
    {
      $limitstring = "";
      if(!is_null($data) && array_key_exists("nodeid", $data) && is_numeric($data["nodeid"])) $nodeid = $data["nodeid"];

      try{
        if(is_null($nodeid)){
          $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash, cis.id AS sysinfoid, cis.timestamp,cis.load_1min,cis.load_5min,cis.load_15min,cis.memory_total,cis.memory_free, cis.memory_buffers,cis.memory_shared,cis.memory_cached,cis.swap_total,cis.swap_free,cis.cpu_count,cis.cpu_cores,cis.cpu_model, cicu.cpu_number, cicu.cpu_usage, cis.os_type, cis.os_name, cisf.device, cisf.size, cisf.used, cisf.avail, cisf.mountpoint
                                          FROM nodes n
                                          LEFT JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id AND cis.timestamp = (SELECT max(cis1.timestamp) FROM chia_infra_sysinfo cis1 WHERE cis1.nodeid = n.id)
                                          LEFT JOIN chia_infra_sysinfo_filesystems cisf ON cisf.sysinfo_id = cis.id
                                          LEFT JOIN chia_infra_cpu_usage cicu ON cicu.sysinfo_id = cis.id
                                          WHERE n.id = (
                                            SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
                                          )", array());
        }else{
          $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash, cis.id AS sysinfoid, cis.timestamp,cis.load_1min,cis.load_5min,cis.load_15min,cis.memory_total,cis.memory_free, cis.memory_buffers,cis.memory_shared,cis.memory_cached,cis.swap_total,cis.swap_free,cis.cpu_count,cis.cpu_cores,cis.cpu_model, cicu.cpu_number, cicu.cpu_usage, cis.os_type, cis.os_name, cisf.device, cisf.size, cisf.used, cisf.avail, cisf.mountpoint
                                          FROM nodes n
                                          LEFT JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id AND cis.timestamp = (SELECT max(cis1.timestamp) FROM chia_infra_sysinfo cis1 WHERE cis1.nodeid = n.id)
                                          LEFT JOIN chia_infra_sysinfo_filesystems cisf ON cisf.sysinfo_id = cis.id
                                          LEFT JOIN chia_infra_cpu_usage cicu ON cicu.sysinfo_id = cis.id
                                          WHERE n.id = ?", array($data["nodeid"]));
        }
        
        $returnarray = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $sysinfodata){
          if(!array_key_exists($sysinfodata["id"], $returnarray)){
            $returnarray[$sysinfodata["id"]] = $sysinfodata;
            $returnarray[$sysinfodata["id"]]["nodeauthhash"] = $this->encryption_api->decryptString($sysinfodata["nodeauthhash"]);
            $returnarray[$sysinfodata["id"]]["filesystems"][$sysinfodata["mountpoint"]] = [$sysinfodata["device"], $sysinfodata["size"], $sysinfodata["used"], $sysinfodata["avail"], $sysinfodata["mountpoint"]];
            $returnarray[$sysinfodata["id"]]["cpu_usages"][$sysinfodata["cpu_number"]] = $sysinfodata["cpu_usage"];
            unset(
              $returnarray[$sysinfodata["id"]]["device"],
              $returnarray[$sysinfodata["id"]]["size"],
              $returnarray[$sysinfodata["id"]]["used"],
              $returnarray[$sysinfodata["id"]]["avail"],
              $returnarray[$sysinfodata["id"]]["mountpoint"]
            ); 
          }else{
            //array_push($returnarray[$sysinfodata["id"]]["filesystems"], [$sysinfodata["device"], $sysinfodata["size"], $sysinfodata["used"], $sysinfodata["avail"], $sysinfodata["mountpoint"]]);
            $returnarray[$sysinfodata["id"]]["filesystems"][$sysinfodata["mountpoint"]] = [$sysinfodata["device"], $sysinfodata["size"], $sysinfodata["used"], $sysinfodata["avail"], $sysinfodata["mountpoint"]];
            $returnarray[$sysinfodata["id"]]["cpu_usages"][$sysinfodata["cpu_number"]] = $sysinfodata["cpu_usage"];
          }
        }

        return array("status" => 0, "message" => "Successfully loaded latest system information.", "data" => $returnarray);
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
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
    public function querySystemInfo(array $data = NULL, array $loginData = NULL, $server = NULL): array
    {
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
        return $server->$callfunction($querydata);
      }else{
        $this->websocket_api = new WebSocket_Api();
        return $this->websocket_api->sendToWSS($callfunction, $querydata);
      }
    }

    /**
     * Returns all or node specific available services.
     *
     * @param array $data
     * @return array
     */
    public function getAvailableServices(array $data = []): array
    {     
      try{
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
          if(is_null($data["service_target"])){
            $where_statement .= "(cias.service_target IS NULL OR cias.service_target = '') AND ";
          }else{
            $where_statement .= "cias.service_target = ? AND ";
            array_push($statement_array, $data["service_target"]);
          }
        }

        $sql = $this->db_api->execute("SELECT cias.id, cias.curr_service_insert_id, cias.service_target ,cias.service_type, cias.refers_to_rule_id, ar.rule_target, ar.warn_at_after, ar.crit_at_after, cias.service_state, cias.percent_used, cias.node_id, ar.monitor, cias.service_state_first_reported, cias.service_state_last_reported
                                        FROM chia_infra_available_services cias 
                                        JOIN alerting_rules ar ON ar.id = cias.refers_to_rule_id
                                        WHERE $where_statement service_state_first_reported = (SELECT max(cias1.service_state_first_reported) FROM chia_infra_available_services cias1 WHERE cias1.node_id = cias.node_id AND cias1.service_type = cias.service_type AND cias1.service_target = cias.service_target)", $statement_array);
        $found_available_services = $sql->fetchAll(\PDO::FETCH_ASSOC);

        $found_data = [
          "by-avail-serv-id" => [],
          "by-avail-serv-target" => []
        ];
        foreach($found_available_services AS $arrkey => $found_service){
          if(!array_key_exists($found_service["node_id"], $found_data["by-avail-serv-id"])) $found_data["by-avail-serv-id"][$found_service["node_id"]] = [];
          if(!array_key_exists($found_service["service_type"], $found_data["by-avail-serv-id"][$found_service["node_id"]])) $found_data["by-avail-serv-id"][$found_service["node_id"]][$found_service["service_type"]] = [];
          $found_data["by-avail-serv-id"][$found_service["node_id"]][$found_service["service_type"]][$found_service["id"]] = $found_service;
          
          $rule_node_target = (is_null($found_service["rule_target"]) ? "All" : $found_service["rule_target"]);
          if(!array_key_exists($found_service["node_id"], $found_data["by-avail-serv-target"])) $found_data["by-avail-serv-target"][$found_service["node_id"]] = [];
          if(!array_key_exists($found_service["service_type"], $found_data["by-avail-serv-target"][$found_service["node_id"]])) $found_data["by-avail-serv-target"][$found_service["node_id"]][$found_service["service_type"]] = [];
          if(!array_key_exists($rule_node_target, $found_data["by-avail-serv-target"][$found_service["node_id"]][$found_service["service_type"]])) $found_data["by-avail-serv-target"][$found_service["node_id"]][$found_service["service_type"]][$rule_node_target] = [];
          $found_data["by-avail-serv-target"][$found_service["node_id"]][$found_service["service_type"]][$rule_node_target][$found_service["id"]] = $found_service;
        }

        return array("status" => 0, "message" => "Successfully loaded available services.", "data" => $found_data);
      }catch(\Exception $e){
        //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Inserts a new available sysinfo service or updates an existing one.
     *
     * @param array $data
     * @return array
     */
    public function updateAvailableServices(array $data): array
    {
      if(array_key_exists("node_id", $data) && array_key_exists("service_type_id", $data) && array_key_exists("service_insert_id", $data) && 
        array_key_exists("defined_maximum", $data) && array_key_exists("current_service_level", $data) && 
        ($data["service_type_id"] == 9 && array_key_exists("service_target", $data)) || $data["service_type_id"] < 9){

        $nodeid = $data["node_id"];
        $this_service_type_id = $data["service_type_id"];
        $this_service_target = (array_key_exists("service_target", $data) && !is_null($data["service_target"]) ? $data["service_target"] : NULL);

        
        $this_service_alerting_infos = $this->alerting_api->getRuleInformationOfService(["service_type_id" => $this_service_type_id, "node_id" => $nodeid, "service_target" => $this_service_target])["data"];

        $service_insert_id = $data["service_insert_id"];
        $defined_maximum = $data["defined_maximum"];
        $current_service_level = $data["current_service_level"];
        $perc_or_min_value = $this_service_alerting_infos["perc_or_min_value"];
        $warn_level_at_after = $this_service_alerting_infos["warn_at_after"];
        $crit_level_at_after = $this_service_alerting_infos["crit_at_after"];
        
        $found_node_available_services = $this->getAvailableServices(["node_id" => $nodeid, "service_type_id" => $this_service_type_id, "service_target" => $this_service_target])["data"];
        $current_service_alerting_level = $this->alerting_api->calculateAlertingLevel(["defined_maximum" => $defined_maximum, "current_service_level" => $current_service_level, "perc_or_min_value" => $perc_or_min_value, "warn_level_at_after" => $warn_level_at_after, "crit_level_at_after" => $crit_level_at_after])["data"];

        try{
          $insert_new = false;
          if(array_key_exists($nodeid, $found_node_available_services["by-avail-serv-id"]) &&
            array_key_exists($this_service_type_id ,$found_node_available_services["by-avail-serv-id"][$nodeid]) && 
            count($found_node_available_services["by-avail-serv-id"][$nodeid][$this_service_type_id]) > 0){
            
            $found_avail_service = $found_node_available_services["by-avail-serv-id"][$nodeid][$this_service_type_id];
            $target_avail_serv_id = array_key_first($found_avail_service);
            $target_avail_service = $found_avail_service[$target_avail_serv_id];

            $sql = $this->db_api->execute("UPDATE chia_infra_available_services SET curr_service_insert_id = ?, percent_used = ?, service_state_last_reported = NOW() WHERE id = ?",
                                            array($service_insert_id, $current_service_alerting_level["percent_usage"], $target_avail_serv_id));
            
            if($target_avail_service["service_state"] != $current_service_alerting_level["level"]){
              $insert_new = true;
            }
          }else{
            $insert_new = true;
          }
  
          if($insert_new){
            $sql = $this->db_api->execute("INSERT INTO chia_infra_available_services (id, curr_service_insert_id, service_target, service_type, refers_to_rule_id, service_state, percent_used, node_id, service_state_first_reported, service_state_last_reported) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())", 
                                          array($service_insert_id, $this_service_target, $this_service_type_id, $this_service_alerting_infos["id"], $current_service_alerting_level["level"], $current_service_alerting_level["percent_usage"], $nodeid));
          }

          return array("status" => 0, "message" => "Successfully updated available services with new information.");
        }catch(\Exeption $e){
          //TODO Implement correct status codes
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
  
      }else{
        return array("status" => 1, "messages" => "Not all data stated.");
      }

    }
  }
?>
