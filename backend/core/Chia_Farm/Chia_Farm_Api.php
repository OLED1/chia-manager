<?php
  namespace ChiaMgmt\Chia_Farm;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  use ChiaMgmt\Encryption\Encryption_Api;

  /**
   * The Chia_Farm_Api class contains every needed methods to manage all available farming data.
   * This class is used by the client to send in data and from the webclient to get data.
   * The client can also be managed via this class.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Chia_Farm_Api{
    /**
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;
    /**
     * Holds an instance to the Logging Class.
     * @var Logging_Api
     */
    private $logging_api;
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
      $this->nodes_api = new Nodes_Api();
      $this->encryption_api = new Encryption_Api();
      $this->server = $server;
    }

    /**
     * Update the available farm data.
     * Function made for: Node Client
     * @throws Exception $e       Throws an exception on db errors.
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-Encryption-Encryption-Api.html#method_encryptString
     * @param  array  $data       {"farm": {"farming_status": "Not available", "plot_count_for_all_harvesters": "0", "total_size_of_plots": "0.000 MiB", "estimated_network_space": "Unknown", "expected_time_to_win": "Never (no plots)", "challenges": []}}
     * @param  array  $loginData  {"logindata": {"authhash": "[authhash]"}
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {"nodeid": [nodeid], "data": {[newly added farm data]}}
     */
    public function updateFarmData(array $data, array $loginData = NULL){
      if(array_key_exists("farm", $data) && array_key_exists("farming_status", $data["farm"])){
        try{
          $farmdata = $data["farm"];
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
          $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

          $sql = $this->db_api->execute("SELECT Count(*) as count FROM chia_farm WHERE nodeid = ?", array($nodeid));
          $count = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["count"];

          if(array_key_exists("total_chia_farmed", $farmdata)){
            $totalchiafarmed = $farmdata["total_chia_farmed"];
            $usertransactionfees = $farmdata["user_transaction_fees"];
            $blockrewards = $farmdata["block_rewards"];
            $lastheigthfarmed = $farmdata["last_height_farmed"];
          }else{
            $totalchiafarmed = 0;
            $usertransactionfees = 0;
            $blockrewards = 0;
            $lastheigthfarmed = 0;
          }

          if($count == 0){
            $sql = $this->db_api->execute("INSERT INTO chia_farm (id, nodeid, farming_status, total_chia_farmed, user_transaction_fees, block_rewards, last_height_farmed, plot_count, total_size_of_plots, estimated_network_space, expected_time_to_win) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array($nodeid, $farmdata["farming_status"], $totalchiafarmed, $usertransactionfees, $blockrewards, $lastheigthfarmed, $farmdata["plot_count_for_all_harvesters"], $farmdata["total_size_of_plots"], $farmdata["estimated_network_space"], $farmdata["expected_time_to_win"]));
          }else{
            $sql = $this->db_api->execute("UPDATE chia_farm SET farming_status = ?, total_chia_farmed = ?, user_transaction_fees = ?, block_rewards = ?, last_height_farmed = ?, plot_count = ?, total_size_of_plots = ?, estimated_network_space = ?, expected_time_to_win = ? WHERE nodeid = ?",
            array($farmdata["farming_status"], $totalchiafarmed, $usertransactionfees, $blockrewards, $lastheigthfarmed, $farmdata["plot_count_for_all_harvesters"], $farmdata["total_size_of_plots"], $farmdata["estimated_network_space"], $farmdata["expected_time_to_win"], $nodeid));
          }

          $this->updateChallenges($data["farm"], $loginData);
        }catch(Exception $e){
          return $this->logging->getErrormessage("001", $e);
        }

        return array("status" => 0, "message" => "Successfully updated farm information for node $nodeid.", "data" => ["nodeid" => $nodeid, "data" => $this->getFarmData($data, $loginData, $nodeid)["data"]]);
      }else{
        return $this->logging->getErrormessage("002");
      }
    }

    /**
     * Returns currently saved farm data values from the api.
     * Function made for: Web GUI/App
     * @throws Exception $e                   Throws an exception on db errors.
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-Encryption-Encryption-Api.html#method_decryptString
     * @param  array $data                    { NULL } Will be changed to { nodeid: [NULL|nodeid] } as soon as the method needs to be called outsite of the web gui.
     * @param  array $loginData               { NULL } No logindata will be needed to be able to return valid data.
     * @param  ChiaWebSocketServer $server    An instance to websocket server class to be able to send data directly to nodes.
     * @param  int $nodeid                    The node id to get only node specific data. Can be NULL if all data will be queried. Will be deprecated as soon as the method needs to be called outsite of the web gui.
     * @return array                          Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function getFarmData(array $data = NULL, array $loginData = NULL, $server = NULL, int $nodeid = NULL){
      try{
        if(is_null($nodeid)){
          $sql = $this->db_api->execute("SELECT nt.nodeid, cf.farming_status, n.hostname, n.nodeauthhash, cf.total_chia_farmed, cf.user_transaction_fees, cf.block_rewards, cf.last_height_farmed, cf.plot_count, cf.total_size_of_plots, cf.estimated_network_space, cf.expected_time_to_win, cf.querydate
                                          FROM nodetype nt
                                          JOIN nodes n ON n.id = nt.nodeid
                                          LEFT JOIN chia_farm cf ON cf.nodeid = nt.nodeid
                                          WHERE nt.code = 3"
                                          , array());
        }else{
          $sql = $this->db_api->execute("SELECT nt.nodeid, cf.farming_status, n.hostname, n.nodeauthhash, cf.total_chia_farmed, cf.user_transaction_fees, cf.block_rewards, cf.last_height_farmed, cf.plot_count, cf.total_size_of_plots, cf.estimated_network_space, cf.expected_time_to_win, cf.querydate
                                          FROM nodetype nt
                                          JOIN nodes n ON n.id = nt.nodeid
                                          LEFT JOIN chia_farm cf ON cf.nodeid = nt.nodeid
                                          WHERE nt.code = 3 AND nt.nodeid = ?"
                                          , array($nodeid));
        }

        $returndata = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $farminfo){
          $farminfo["nodeauthhash"] = $this->encryption_api->decryptString($farminfo["nodeauthhash"]);
          $returndata[$farminfo["nodeid"]] = $farminfo;
        }

        return array("status" =>0, "message" => "Successfully loaded chia farm information.", "data" => $returndata);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    /**
     * Sets the current farmerstatus sent in from the node client.
     * Function made for: Node Client
     * @throws Exception $e       Throws an exception on db errors.
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-Encryption-Encryption-Api.html#method_encryptString
     * @param  array $data       { status: [0 = Running |1 = Not Running] } No data is needed to query this method.
     * @param  array $loginData  { NULL } No logindata is needed to query this method.
     * @return array             Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function farmerStatus(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $this->nodes_api->setNodeServiceStats(["type" => 3, "stat" => ($data["status"] == 0 ? 0 : 1), "nodeid" => $nodeid]);

        $data["data"] = $nodeid;
        return array("status" => 0, "message" => "Successfully queried farmer status information for node $nodeid.", "data" => $data);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    /**
     * Informs the node client to query new farm data.
     * Function made for: Communication WebGUI -> Node Client
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-WebSocketServer-ChiaWebSocketServer.html#method_messageSpecificNode
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-WebSocketServer-ChiaWebSocketServer.html#method_messageAllNodes
     * @param  array $data                  { authhash: [Target Node Authhash] }
     * @param  array $loginData             { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server  An instance to the websocket server to be able to send data to the connected clients.
     * @return array                        Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}} from the subfunction calls.
     */
    public function queryFarmData(array $data = NULL, array $loginData = NULL, $server = NULL){
      $querydata = [];
      $querydata["data"]["queryFarmData"] = array(
        "status" => 0,
        "message" => "Query Farm data.",
        "data"=> array()
      );

      $callfunction = "messageAllNodes";
      if(array_key_exists("nodeinfo", $querydata) && array_key_exists("authhash", $querydata["nodeinfo"])){
        $querydata["nodeinfo"]["authhash"] = $data["authhash"];
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
     * Informs the node client to restart the farmer service.
     * Function made for: Communication WebGUI -> Node Client
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-WebSocketServer-ChiaWebSocketServer.html#method_messageSpecificNode
     * @param  array $data                    { authhash: [Target Node Authhash] }
     * @param  array $loginData               { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server    An instance to the websocket server to be able to send data to the connected clients.
     * @return array                          Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}} from the subfunction call.
     */
    public function restartFarmerService(array $data = NULL, array $loginData = NULL, $server = NULL){
      $querydata = [];
      $querydata["data"]["restartFarmerService"] = array(
        "status" => 0,
        "message" => "Restart farmer service.",
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

    /**
     * The function which will be called from the node client when the service has been restarted.
     * Function made for: Node Client
     * @throws Exception $e       Throws an exception on db errors.
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-Encryption-Encryption-Api.html#method_encryptString
     * @param  array $data      { "status": [0 = Success, 1 = Failed], "message": [Specific message about service restart for the WebGUI] }
     * @param  array $loginData { authhash: [Querying Node's Authhash] }
     * @return array            Returns {"status": [0|>0], "message": [Status message], "data": { "status": [0 = Success, 1 = Failed], "message": [Specific message about service restart for the WebGUI], nodeid: [Querying Node's ID] }}
     */
    public function farmerServiceRestart(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried farmer service restart for node $nodeid.", "data" => $data);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    /**
     * Updates the latest challenges.
     * Function made for: Node ClientWeb GUI/App
     * @param  array $data      { challenges: [Latest Challenges as array] }
     * @param  array $loginData { NULL } No logindata needed to query this function.
     * @return array            Returns {"status": [0|>0], "message": [Status message] }
     */
    public function updateChallenges(array $data = NULL, array $loginData = NULL){
      if(array_key_exists("challenges", $data)){
        try{
          $now = new \DateTime("now");

          $sql = $this->db_api->execute("DELETE FROM chia_farm_challenges", array());

          foreach ($data["challenges"] as $arrkey => $challenge) {
            $exploded = explode(" ", $challenge);
            $sql = $this->db_api->execute("INSERT INTO chia_farm_challenges (id, date, hash, hash_index) VALUES (NULL, ?, ?, ?)",
                                          array($now->format("Y-m-d H:i:s"), $exploded[1], $exploded[3]));
          }

          return array("status" => 0, "message" => "Successfully updated challenges information.");
        }catch(Exception $e){
          return $this->logging->getErrormessage("001", $e);
        }
      }else{
        return $this->logging->getErrormessage("002");
      }
    }

    /**
     * Returns all found challenges from the database.
     * Function made for: Web GUI/App
     * @param  array $data      { NULL } No data needed to query this function.
     * @param  array $loginData { NULL } No logindata needed to query this function.
     * @return array            Returns {"status": [0|>0], "message": [Status message] }
     */
    public function getAllChallenges(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT date, hash, hash_index FROM chia_farm_challenges", array());

        return array("status" =>0, "message" => "Successfully queried all challenges.", "data" => $sql->fetchAll(\PDO::FETCH_ASSOC));
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }
  }
?>
