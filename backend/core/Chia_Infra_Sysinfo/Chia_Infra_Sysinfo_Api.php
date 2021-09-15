<?php
  namespace ChiaMgmt\Chia_Infra_Sysinfo;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Encryption\Encryption_Api;

  /**
   * The Chia_Infra_Sysinfo_Api class contains every needed methods to manage all available system specific performance data.
   * It stores and manages values regarding system load, ram, swap and filesystems.
   * This class is used by the client to send in data and from the webclient to get data.
   * The client can also be managed via this class.
   * @version 0.1.1
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
     * Holds an instance to the Encryption Class.
     * @var Encryption_Api
     */
    private $encryption_api;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(){
      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
      $this->encryption_api = new Encryption_Api();
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
    public function updateSystemInfo(array $data, array $loginData = NULL){
      if(array_key_exists("system", $data)){
        try{
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
          $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

          $sql = $this->db_api->execute("INSERT INTO chia_infra_sysinfo (id, nodeid, load_1min, load_5min, load_15min, filesystem, memory_total, memory_free, memory_buffers, memory_cached, swap_total, swap_free, cpu_count, cpu_cores, cpu_model) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
          array($nodeid, $data["system"]["load"]["1min"], $data["system"]["load"]["5min"], $data["system"]["load"]["15min"],
                json_encode($data["system"]["filesystem"]),
                $data["system"]["memory"]["total"], $data["system"]["memory"]["free"], $data["system"]["memory"]["buffers"], $data["system"]["memory"]["cached"],
                $data["system"]["swap"]["total"], $data["system"]["swap"]["free"],
                $data["system"]["cpu"]["count"], $data["system"]["cpu"]["cores"], $data["system"]["cpu"]["model"]
          ));

          return array("status" => 0, "message" => "Successfully updated system information for node $nodeid.", "data" => ["nodeid" => $nodeid]);
        }catch(Exception $e){
          return $this->logging->getErrormessage("001", $e);
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
    public function getSystemInfo(array $data = NULL, array $loginData = NULL, $server = NULL, int $nodeid = NULL){
        try{
          if(is_null($nodeid)){
            $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash, cif.timestamp, cif.load_1min, cif.load_5min, cif.load_15min,
                                                  cif.filesystem, cif.memory_total, cif.memory_free, cif.memory_buffers,
                                                  cif.memory_cached, cif.swap_total, cif.swap_free, cif.cpu_count,
                                                  cif.cpu_cores, cif.cpu_model
                                            FROM nodes n
                                            LEFT JOIN chia_infra_sysinfo cif ON cif.nodeid = n.id AND cif.timestamp = (SELECT max(cif1.timestamp) FROM chia_infra_sysinfo cif1 WHERE cif1.nodeid = n.id)
                                            WHERE n.id = (
                                                SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
                                            )", array());
          }else{
            $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash, cif.timestamp, cif.load_1min,
                                                  cif.load_5min, cif.load_15min, cif.filesystem, cif.memory_total,
                                                  cif.memory_free, cif.memory_buffers, cif.memory_cached,
                                                  cif.swap_total, cif.swap_free, cif.cpu_count, cif.cpu_cores, cif.cpu_model
                                            FROM nodes n
                                            LEFT JOIN chia_infra_sysinfo cif ON cif.nodeid = n.id AND cif.timestamp = (SELECT max(cif1.timestamp) FROM chia_infra_sysinfo cif1 WHERE cif1.nodeid = n.id)
                                            WHERE n.id = ?
                                            )", array($data["nodeid"]));
          }

          $returnarray = [];
          foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $sysinfodata){
            $returnarray[$sysinfodata["id"]] = $sysinfodata;
            $returnarray[$sysinfodata["id"]]["nodeauthhash"] = $this->encryption_api->decryptString($sysinfodata["nodeauthhash"]);

          }

          return array("status" => 0, "message" => "Successfully loaded latest system information.", "data" => $returnarray);
        }catch(Exception $e){
          return $this->logging->getErrormessage("001", $e);
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
    public function querySystemInfo(array $data = NULL, array $loginData = NULL, $server = NULL){
      $querydata = [];
      $querydata["data"]["querySystemInfo"] = array(
        "status" => 0,
        "message" => "Query systeminfo data.",
        "data"=> array()
      );
      $querydata["nodeinfo"]["authhash"] = $data["authhash"];

      if(!is_null($server)){
        return $server->messageSpecificNode($querydata);
      }else{
        $this->websocket_api = new WebSocket_Api();
        return $this->websocket_api->sendToWSS("messageSpecificNode", $querydata);
      }
    }
  }

?>
