<?php
  namespace ChiaMgmt\Chia_Farm;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  use ChiaMgmt\Encryption\Encryption_Api;
  use ChiaMgmt\Chia_Farm\Data_Objects\Farmdata;
  use ChiaMgmt\Chia_Farm\Data_Objects\SignagePointsData;

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
    public function updateFarmData(array $data, array $loginData): array
    {
      try{
        $formatted_data = new Farmdata($data);

        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $sql = $this->db_api->execute("SELECT Count(*) as count FROM chia_farm WHERE nodeid = ?", array($nodeid));
        $count = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["count"];

        if($count == 0){
          $sql = $this->db_api->execute("INSERT INTO chia_farm (id, nodeid, syncstatus, total_chia_farmed, user_transaction_fees, block_rewards, last_height_farmed, plot_count, total_size_of_plots, estimated_network_space, expected_time_to_win, querydate) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, current_timestamp())",
          array($nodeid, $formatted_data->get_farming_status(), $formatted_data->get_total_chia_farmed(), $formatted_data->get_user_transaction_fees(), 
                $formatted_data->get_block_rewards(), $formatted_data->get_last_height_farmed(), $formatted_data->get_plot_count(), $formatted_data->get_total_size_of_plots(), 
                $formatted_data->get_estimated_network_space(), $formatted_data->get_expected_time_to_win()));
        }else{
          $sql = $this->db_api->execute("UPDATE chia_farm SET syncstatus = ?, total_chia_farmed = ?, user_transaction_fees = ?, block_rewards = ?, last_height_farmed = ?, plot_count = ?, total_size_of_plots = ?, estimated_network_space = ?, expected_time_to_win = ?, querydate = current_timestamp() WHERE nodeid = ?",
          array($formatted_data->get_farming_status(), $formatted_data->get_total_chia_farmed(), $formatted_data->get_user_transaction_fees(), 
                $formatted_data->get_block_rewards(), $formatted_data->get_last_height_farmed(), $formatted_data->get_plot_count(), $formatted_data->get_total_size_of_plots(), 
                $formatted_data->get_estimated_network_space(), $formatted_data->get_expected_time_to_win(), $nodeid));
        }

        $this->updateChallenges($data["signage_points"], $loginData);
        return array("status" => 0, "message" => "Successfully updated farm information for node $nodeid.", "data" => ["nodeid" => $nodeid]);
      }catch(\Exception $e){
        print_r(array("status" => 1, "message" => "An error occured: {$e->getMessage()}"));
        return $this->logging_api->getErrormessage("002", $e);
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
    public function getFarmData(array $data = [], array $loginData = NULL, $server = NULL, int $nodeid = NULL): array
    {
      if(!is_null($data) && array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 0) $nodeid = $data["nodeid"];
      try{
        if(is_null($nodeid)){
          $sql = $this->db_api->execute("SELECT nt.nodeid, cf.syncstatus, n.hostname, n.nodeauthhash, cf.total_chia_farmed, cf.user_transaction_fees, cf.block_rewards, cf.last_height_farmed, cf.plot_count, cf.total_size_of_plots, cf.estimated_network_space, cf.expected_time_to_win, cf.querydate
                                          FROM nodetype nt
                                          JOIN nodes n ON n.id = nt.nodeid
                                          LEFT JOIN chia_farm cf ON cf.nodeid = nt.nodeid
                                          WHERE nt.code = 3"
                                          , array());
        }else{
          $sql = $this->db_api->execute("SELECT nt.nodeid, cf.syncstatus, n.hostname, n.nodeauthhash, cf.total_chia_farmed, cf.user_transaction_fees, cf.block_rewards, cf.last_height_farmed, cf.plot_count, cf.total_size_of_plots, cf.estimated_network_space, cf.expected_time_to_win, cf.querydate
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
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
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
    public function queryFarmData(array $data = NULL, array $loginData = NULL, $server = NULL): array
    {
      $querydata = [];
      $querydata["data"]["queryFarmData"] = array(
        "status" => 0,
        "message" => "Query Farm data.",
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
     * Informs the node client to restart the farmer service.
     * Function made for: Communication WebGUI -> Node Client
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-WebSocketServer-ChiaWebSocketServer.html#method_messageSpecificNode
     * @param  array $data                    { authhash: [Target Node Authhash] }
     * @param  array $loginData               { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server    An instance to the websocket server to be able to send data to the connected clients.
     * @return array                          Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}} from the subfunction call.
     */
    public function restartFarmerService(array $data = NULL, array $loginData = NULL, $server = NULL): array
    {
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
    public function farmerServiceRestart(array $data = NULL, array $loginData = NULL): array
    {
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried farmer service restart for node $nodeid.", "data" => $data);
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    /**
     * Updates the latest challenges.
     * Function made for: Node ClientWeb GUI/App
     * @param  array $data      { challenges: [Latest Challenges as array] }
     * @param  array $loginData { NULL } No logindata needed to query this function.
     * @return array            Returns {"status": [0|>0], "message": [Status message] }
     */
    public function updateChallenges(array $data = NULL, array $loginData = NULL): array
    {
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        if(is_numeric($nodeid) && $nodeid > 0){
          $valuesstring = "";
          $valuesarray = [];
          foreach($data AS $arrkey => $this_signage_point){
            $this_signage_point_formatted = new SignagePointsData($this_signage_point);
            $sql = $this->db_api->execute("INSERT INTO chia_farm_challenges (id,nodeid,date,challenge_chain_sp,challenge_hash,difficulty,reward_chain_sp,signage_point_index,sub_slot_iters) VALUES (NULL, ?, current_timestamp(), ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE date = current_timestamp()", [$nodeid, $this_signage_point_formatted->get_challenge_chain_sp(), $this_signage_point_formatted->get_challenge_hash(), $this_signage_point_formatted->get_difficulty(), 
            $this_signage_point_formatted->get_reward_chain_sp(), $this_signage_point_formatted->get_signage_point_index(), $this_signage_point_formatted->get_sub_slot_iters()]);
          }
   
          return array("status" => 0, "message" => "Successfully updated challenges information.");
        }else{
          return $this->logging_api->getErrormessage("001");
        }
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("002", $e);
      }
    }

    /**
     * Returns all found challenges from the database.
     * Function made for: Web GUI/App
     * @param  array $data      { NULL } No data needed to query this function.
     * @param  array $loginData { NULL } No logindata needed to query this function.
     * @return array            Returns {"status": [0|>0], "message": [Status message], "data" => [DB fond data] }
     */
    public function getChallenges(array $data = NULL, array $loginData = NULL): array
    {
      $limit = "";
      $nodeid = "";
      if(array_key_exists("limit", $data) && is_numeric($data["limit"]) && $data["limit"] > 0) $limit = "LIMIT {$data["limit"]}";
      if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 0) $nodeid = "AND n.id = {$data["nodeid"]}";

      try{
        $sql = $this->db_api->execute("SELECT cfc.id, n.id AS nodeid, cfc.date, cfc.challenge_chain_sp, cfc.challenge_hash, cfc.difficulty, cfc.reward_chain_sp, cfc.signage_point_index, cfc.sub_slot_iters
                                        FROM nodes n
                                        LEFT JOIN LATERAL (
                                            SELECT * FROM chia_farm_challenges WHERE nodeid = n.id ORDER BY date DESC {$limit}
                                        ) as cfc
                                        ON cfc.nodeid = n.id
                                        WHERE n.authtype = 2 {$nodeid}", array());
        $foundchallenges = $sql->fetchAll(\PDO::FETCH_ASSOC);
        
        $returndata = [];
        foreach($foundchallenges AS $arrkey => $thischallenge){
          if(!array_key_exists($thischallenge["nodeid"], $returndata)) $returndata[$thischallenge["nodeid"]] = [];
          array_push($returndata[$thischallenge["nodeid"]], $thischallenge);
        }

        return array("status" => 0, "message" => "Successfully queried all challenges.", "data" => $returndata);
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }
  }
?>
