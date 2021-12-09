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

    public function getSystemsLoadHistory(array $data){
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

    public function getRAMSwapHistory(array $data){
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

    public function getFilesystemsHistory(array $data){
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
  }
