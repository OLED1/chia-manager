<?php
  namespace ChiaMgmt\Alerting\Additional_Functions;
  use ChiaMgmt\DB\DB_Api;

  class AlertingServices{
    /**
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;

    public function __construct(){
      $this->db_api = new DB_Api();
    }

    /**
     * Returns the configurable uptstatus services from all or a specific node. Function needed for alerting.
     *
     * @param array $data
     * @return array
     */
    public function getSystemUpstatusServices(array $data = []): array
    {
      $wherestatement = "";
      $parameter_array = [];
      if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 2){
        $wherestatement .= " AND n.id = ?";
        array_push($parameter_array, $data["nodeid"]);
      }

      try{
        $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash
                                      FROM nodes n
                                      JOIN nodetype nt ON nt.nodeid = n.id
                                      JOIN nodetypes_avail nta ON nt.code = nta.code
                                      WHERE n.id = (
                                        SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
                                      ) AND 1 NOT IN (SELECT rule_type FROM alerting_rules WHERE system_target = n.id AND rule_type = 1){$wherestatement}
                                      GROUP BY n.id", $parameter_array);

        $returnarray = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $upstatus_infos){
          if(!array_key_exists($upstatus_infos["id"], $returnarray)) $returnarray[$upstatus_infos["id"]] = [];
          if(!array_key_exists("configurable_services", $returnarray[$upstatus_infos["id"]])) $returnarray[$upstatus_infos["id"]]["configurable_services"] = [];
          array_push($returnarray[$upstatus_infos["id"]]["configurable_services"], "total downtime");
        }
                                        
        return array("status" => 0, "message" => "Successfully loaded latest load information.", "data" => $returnarray);                                 
      }catch(\Exception $e){
        //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Returns the configurable farmer services from all or a specific node. Function needed for alerting.
     *
     * @param array $data
     * @return array
     */
    public function getFarmerServices(array $data = []): array
    {
      $wherestatement = "";
      $parameter_array = [];
      if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 2){
        $wherestatement .= " AND n.id = ?";
        array_push($parameter_array, $data["nodeid"]);
      }

      try{
        $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash, nta.code, nta.description
                                      FROM nodes n
                                      JOIN nodetype nt ON nt.nodeid = n.id
                                      JOIN nodetypes_avail nta ON nt.code = nta.code
                                      WHERE n.id = (
                                        SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
                                      ) AND 2 NOT IN (SELECT rule_type FROM alerting_rules WHERE system_target = n.id AND rule_type = 2) AND nta.code = 3{$wherestatement}", $parameter_array);

        $returnarray = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $farmer_infos){
          if(!array_key_exists($farmer_infos["id"], $returnarray)) $returnarray[$farmer_infos["id"]] = [];
          if(!array_key_exists("configurable_services", $returnarray[$farmer_infos["id"]])) $returnarray[$farmer_infos["id"]]["configurable_services"] = [];
          array_push($returnarray[$farmer_infos["id"]]["configurable_services"], "total downtime");
        }
                                        
        return array("status" => 0, "message" => "Successfully loaded latest load information.", "data" => $returnarray);                                 
      }catch(\Exception $e){
        //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Returns the configurable harvester services from all or a specific node. Function needed for alerting.
     *
     * @param array $data
     * @return array
     */
    public function getHarvesterServices(array $data = []): array
    {
      $wherestatement = "";
      $parameter_array = [];
      if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 2){
        $wherestatement .= " AND n.id = ?";
        array_push($parameter_array, $data["nodeid"]);
      }

      try{
        $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash, nta.code, nta.description
                                      FROM nodes n
                                      JOIN nodetype nt ON nt.nodeid = n.id
                                      JOIN nodetypes_avail nta ON nt.code = nta.code
                                      WHERE n.id = (
                                        SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
                                      ) AND 3 NOT IN (SELECT rule_type FROM alerting_rules WHERE system_target = n.id AND rule_type = 3) AND nta.code = 4{$wherestatement}", $parameter_array);

        $returnarray = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $harvester_infos){
          if(!array_key_exists($harvester_infos["id"], $returnarray)) $returnarray[$harvester_infos["id"]] = [];
          if(!array_key_exists("configurable_services", $returnarray[$harvester_infos["id"]])) $returnarray[$harvester_infos["id"]]["configurable_services"] = [];
          array_push($returnarray[$harvester_infos["id"]]["configurable_services"], "total downtime");
        }
                                        
        return array("status" => 0, "message" => "Successfully loaded latest load information.", "data" => $returnarray);                                 
      }catch(\Exception $e){
        //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Returns the configurable wallet service from all or a specific node. Function needed for alerting.
     *
     * @param array $data
     * @return array
     */
    public function getWalletServices(array $data = []): array
    {
      $wherestatement = "";
      $parameter_array = [];
      if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 2){
        $wherestatement .= " AND n.id = ?";
        array_push($parameter_array, $data["nodeid"]);
      }

      try{
        $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash, nta.code, nta.description
                                      FROM nodes n
                                      JOIN nodetype nt ON nt.nodeid = n.id
                                      JOIN nodetypes_avail nta ON nt.code = nta.code
                                      WHERE n.id = (
                                        SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
                                      ) AND 4 NOT IN (SELECT rule_type FROM alerting_rules WHERE system_target = n.id AND rule_type = 4) AND nta.code = 5{$wherestatement}", $parameter_array);

        $returnarray = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $wallet_infos){
          if(!array_key_exists($wallet_infos["id"], $returnarray)) $returnarray[$wallet_infos["id"]] = [];
          if(!array_key_exists("configurable_services", $returnarray[$wallet_infos["id"]])) $returnarray[$wallet_infos["id"]]["configurable_services"] = [];
          array_push($returnarray[$wallet_infos["id"]]["configurable_services"], "total downtime");
        }
                                        
        return array("status" => 0, "message" => "Successfully loaded latest load information.", "data" => $returnarray);                                 
      }catch(\Exception $e){
        //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Returns the configurable CPU Load services from all or a specific node. Function needed for alerting.
     * 
     * @param [type] $data
     * @return array
     */
    public function getCPULoad(array $data = []): array
    {
      $wherestatement = "";
      $parameter_array = [];
      if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 2){
        $wherestatement .= " AND n.id = ?";
        array_push($parameter_array, $data["nodeid"]);
      }

      try{
        $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash, cis.id AS sysinfoid, cis.timestamp, cis.cpu_count, cis.cpu_cores, cis.load_1min,cis.load_5min,cis.load_15min
                                        FROM nodes n
                                        INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id AND cis.timestamp = (SELECT max(cis1.timestamp) FROM chia_infra_sysinfo cis1 WHERE cis1.nodeid = n.id)
                                        WHERE n.id = (
                                              SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
                                        ) AND 5 NOT IN (SELECT rule_type FROM alerting_rules WHERE system_target = n.id AND rule_type = 5){$wherestatement}", $parameter_array);

        $returnarray = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $load_infos){
          if(!array_key_exists($load_infos["id"], $returnarray)) $returnarray[$load_infos["id"]] = [];
          if(!array_key_exists("configurable_services", $returnarray[$load_infos["id"]])) $returnarray[$load_infos["id"]]["configurable_services"] = [];
          array_push($returnarray[$load_infos["id"]]["configurable_services"], "total usage");
        }
                                        
        return array("status" => 0, "message" => "Successfully loaded latest load information.", "data" => $returnarray);                                 
      }catch(\Exception $e){
        //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Returns the configurable CPU Utilisation services from all or a specific node. Function needed for alerting.
     *
     * @param [type] $data
     * @return array
     */
    public function getCPUUtilisation(array $data = []): array
    {
      $wherestatement = "";
      $parameter_array = [];
      if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 2){
        $wherestatement .= " AND n.id = ?";
        array_push($parameter_array, $data["nodeid"]);
      }

      try{
        $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash
                                        FROM nodes n
                                        INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id AND cis.timestamp = (SELECT max(cis1.timestamp) FROM chia_infra_sysinfo cis1 WHERE cis1.nodeid = n.id)
                                        WHERE n.id = (
                                              SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
                                        ) AND 6 NOT IN (SELECT rule_type FROM alerting_rules WHERE system_target = n.id AND rule_type = 6){$wherestatement}", $parameter_array);

        $returnarray = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $cpu_usage_infos){
          if(!array_key_exists($cpu_usage_infos["id"], $returnarray)) $returnarray[$cpu_usage_infos["id"]] = [];
          if(!array_key_exists("configurable_services", $returnarray[$cpu_usage_infos["id"]])) $returnarray[$cpu_usage_infos["id"]]["configurable_services"] = [];
          array_push($returnarray[$cpu_usage_infos["id"]]["configurable_services"], "total usage");
        }
                                        
        return array("status" => 0, "message" => "Successfully loaded latest cpu usage information.", "data" => $returnarray);                                 
      }catch(\Exception $e){
        //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Returns the configurable Memory usage services from all or a specific node. Function needed for alerting.
     *
     * @param [type] $data
     * @return array
     */
    public function getMemoryUsage(array $data = []): array
    {
      $wherestatement = "";
      $parameter_array = [];
      if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 2){
        $wherestatement .= " AND n.id = ?";
        array_push($parameter_array, $data["nodeid"]);
      }

      try{
        $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash, cis.id AS sysinfoid, cis.timestamp, cis.memory_total,cis.memory_free, cis.memory_buffers,cis.memory_shared,cis.memory_cached
                                        FROM nodes n
                                        INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id AND cis.timestamp = (SELECT max(cis1.timestamp) FROM chia_infra_sysinfo cis1 WHERE cis1.nodeid = n.id)
                                        WHERE n.id = (
                                            SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
                                        ) AND 7 NOT IN (SELECT rule_type FROM alerting_rules WHERE system_target = n.id AND rule_type = 7){$wherestatement}", $parameter_array);

        $returnarray = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $memory_usage){
          if(!array_key_exists($memory_usage["id"], $returnarray)) $returnarray[$memory_usage["id"]] = [];
          if(!array_key_exists("configurable_services", $returnarray[$memory_usage["id"]])) $returnarray[$memory_usage["id"]]["configurable_services"] = [];
          array_push($returnarray[$memory_usage["id"]]["configurable_services"], "total usage");
        }
                                        
        return array("status" => 0, "message" => "Successfully loaded latest load information.", "data" => $returnarray);                                 
      }catch(\Exception $e){
        //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Returns the configurable SWAP usage services from all or a specific node. Function needed for alerting.
     *
     * @param [type] $data
     * @return array
     */
    public function getSWAPUsage(array $data = []): array
    {
      $wherestatement = "";
      $parameter_array = [];
      if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 2){
        $wherestatement .= " AND n.id = ?";
        array_push($parameter_array, $data["nodeid"]);
      }

      try{
        $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash, cis.id AS sysinfoid, cis.timestamp, cis.swap_total
                                        FROM nodes n
                                        INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id AND cis.timestamp = (SELECT max(cis1.timestamp) FROM chia_infra_sysinfo cis1 WHERE cis1.nodeid = n.id)
                                        WHERE n.id = (
                                          SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
                                        ) AND 8 NOT IN (SELECT rule_type FROM alerting_rules WHERE system_target = n.id AND rule_type = 8){$wherestatement}", $parameter_array);

        $returnarray = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $swap_usage){
          if(!array_key_exists($swap_usage["id"], $returnarray)) $returnarray[$swap_usage["id"]] = [];
          if(!array_key_exists("configurable_services", $returnarray[$swap_usage["id"]])) $returnarray[$swap_usage["id"]]["configurable_services"] = [];
          array_push($returnarray[$swap_usage["id"]]["configurable_services"], "total usage");
        }
                                        
        return array("status" => 0, "message" => "Successfully loaded latest load information.", "data" => $returnarray);   
      }catch(\Exception $e){
        //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Returns the last Filesystem usages values from all or a specific node. Function needed for alerting.
     *
     * @param [type] $data
     * @return array
     */
    public function getFilesystemsUsage(array $data = []): array
    {
      $wherestatement = "";
      $parameter_array = [];
      if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 2){
        $wherestatement .= " AND n.id = ?";
        array_push($parameter_array, $data["nodeid"]);
      }

      try{
        $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash, cis.id AS sysinfoid, cis.timestamp, cisf.device, cisf.size, cisf.used, cisf.avail, cisf.mountpoint
                                        FROM nodes n
                                        INNER JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id AND cis.timestamp = (SELECT max(cis1.timestamp) FROM chia_infra_sysinfo cis1 WHERE cis1.nodeid = n.id)
                                        INNER JOIN chia_infra_sysinfo_filesystems cisf ON cisf.sysinfo_id = cis.id
                                        WHERE n.id = (
                                          SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
                                        ) AND cisf.mountpoint NOT IN (SELECT rule_target FROM alerting_rules WHERE system_target = n.id){$wherestatement}", $parameter_array);

        $returnarray = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $filesystem_infos){
          if(!array_key_exists($filesystem_infos["id"], $returnarray)) $returnarray[$filesystem_infos["id"]] = [];
          if(!array_key_exists("configurable_services", $returnarray[$filesystem_infos["id"]])) $returnarray[$filesystem_infos["id"]]["configurable_services"] = [];
          array_push($returnarray[$filesystem_infos["id"]]["configurable_services"], $filesystem_infos["mountpoint"]);
        }
                                       
        return array("status" => 0, "message" => "Successfully loaded latest load information.", "data" => $returnarray);   
      }catch(\Exception $e){
        //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }
  }