<?php
  namespace ChiaMgmt\Nodes;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\WebSocket\WebSocket_Api;
  use ChiaMgmt\Encryption\Encryption_Api;

  /**
   * The Nodes_Api class contains every needed methods to manage all available nodes.
   * The following nodes are valid: backend, webclient (app), farmer, harvester, wallet.
   * The last stated types can be used at once.
   * This class is used by the webclient to get data.
   * The client can also be managed via this class.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Nodes_Api{
    /**
     * Holds an instance to the WebSocket Class.
     * @var WebSocket_Api
     */
    private $websocket_api;
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
     * Returns a list of active subscriptions known to the websocket server which saves this data locally.
     * Subscriptions contains a list of clients which are currently logged in and accepted by the api.
     * Function made for: Backend / Web GUI
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[List of nodes of active subscriptions]}
     */
    public function getActiveSubscriptions(){
      $this->websocket_api = new WebSocket_Api();
      return $this->websocket_api->sendToWSS("getActiveSubscriptions")["getActiveSubscriptions"];
    }

    /**
     * Returns a list of active requests known to the websocket server which saves this data locally.
     * Requests contains a list of clients which are currently waiting to get accepted by the api.
     * Function made for: Backend / Web GUI
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[List of nodes of active requests]}
     */
    public function getActiveRequests(){
      $this->websocket_api = new WebSocket_Api();
      return $this->websocket_api->sendToWSS("getActiveRequests")["getActiveRequests"];
    }

    /**
     * Returns an array of all information available for all nodes.
     * Function made for: Web GUI
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB stored node information]}
     */
    public function getConfiguredNodes(){
      $returndata = array();

      try{
        $sql = $this->db_api->execute("SELECT n.id, GROUP_CONCAT(nta.description SEPARATOR ', ') AS nodetype, n.nodeauthhash, n.authtype,
                                        n.conallow, n.hostname, n.scriptversion, n.chiaversion, n.chiapath, n.ipaddress,
                                        n.changeable, n.changedIP, MAX(cis.memory_total) AS memory_total, MAX(cis.swap_total) AS swap_total,
                                        MAX(cis.cpu_cores) AS cpu_cores, MAX(cis.cpu_count) AS cpu_count, MAX(cis.cpu_model) AS cpu_model, lastseen
                                       FROM nodes n
                                       JOIN nodetype nt ON nt.nodeid = n.id
                                       JOIN nodetypes_avail nta ON nta.code = nt.code
                                       LEFT JOIN chia_infra_sysinfo cis ON cis.timestamp = (SELECT MAX(timestamp) FROM chia_infra_sysinfo WHERE nodeid = n.id) AND cis.nodeid = n.id
                                       GROUP BY n.id", array());

        $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);
        $returnarray = array();

        foreach ($sqdata as $arrkey => $conninfo) {
          $returnarray[$conninfo["id"]] = $conninfo;
          $returnarray[$conninfo["id"]]["nodeauthhash"] = $this->encryption_api->decryptString($conninfo["nodeauthhash"]);
        }

        return array("status" => 0, "message" => "Sucessfully loaded all client data.", "data" => $returnarray);
      }
      catch(Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    /**
     * Returns a list of all nodes known and registered to the api.
     * Function made for: Web GUI
     * @throws Exception $e       Throws an exception on db errors.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB stored node type information]}
     */
    public function getNodeTypes(){
      try{
        $sql = $this->db_api->execute("SELECT id, description, code, allowed_authtype, nodetype FROM nodetypes_avail WHERE selectable = 1", array());

        $returndata = array();
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $info){
          $returndata["by-id"][$info["id"]] = $info;
          $returndata["by-desc"][$info["description"]] = $info;
        }

        return array("status" => 0, "message" => "Sucessfully loaded all available nodetypes.", "data" => $returndata);
      }catch(Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    /**
     * Allow a node to reconnect if it's ip has recently changed.
     * Function made for: Web GUI
     * @throws Exception $e                   Throws an exception on db errors.
     * @param  array  $data                   { "nodeid" : [The nodes id where the ip has changed], "authhash" : "[The nodes authhash where the ip has changed]" }
     * @param  array $loginData               { NULL } No logindata is needed to query this function
     * @param  ChiaWebSocketServer $server    An instance to the Webscoket server to be able to communicate with the node
     * @return array                          {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function acceptIPChange(array $data, array $loginData = NULL, $server = NULL){
      if(array_key_exists("nodeid", $data) && array_key_exists("authhash", $data)){
        try{
          $sql = $this->db_api->execute("UPDATE nodes SET ipaddress = changedIP, changedIP = ? WHERE id = ?", array("", $data["nodeid"]));

          $querydata = [];
          $querydata["data"]["acceptIPChange"] = array(
            "status" => 0,
            "message" => "IP Change saved accepted.",
            "data"=> array()
          );

          $querydata["nodeinfo"]["authhash"] = $data["authhash"];
          if(!is_null($server)){
            $server->messageSpecificNode($querydata);
          }else{
            $this->websocket_api = new WebSocket_Api();
            $activeSubscriptions = $this->websocket_api->sendToWSS("messageSpecificNode", $querydata);
          }

          return array("status" => 0, "message" => "IP Change saved for node {$data["nodeid"]}.");
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }
    }

    /**
     * Accept a request of newly connected node.
     * Function made for: Web GUI
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    [description]
     * @param  array $loginData                [description]
     * @param  ChiaWebSocketServer $server     [description]
     * @return array                           [description]
     */
    public function acceptNodeRequest(array $data, array $loginData = NULL, $server = NULL){
      if(array_key_exists("nodeid", $data) && array_key_exists("authhash", $data) && array_key_exists("nodetypes", $data)){
        try{
          $sql = $this->db_api->execute("SELECT id, allowed_authtype, nodetype FROM nodetypes_avail WHERE selectable = 1 AND id IN ({$data["nodetypes"]})", array());
          $sqreturn = $sql->fetchAll(\PDO::FETCH_ASSOC);

          $types = [];
          $allowed_authtype = [];
          foreach ($sqreturn as $arrkey => $nodetypes) {
            $types[$nodetypes["nodetype"]] = 1;
            $allowed_authtype[$nodetypes["allowed_authtype"]] = 1;
          }

          if(count($types) == 1 && count($allowed_authtype) == 1){
            $authtype = $sqreturn[0]["allowed_authtype"];
            $nodeid = $data["nodeid"];
            $authhash = $data["authhash"];

            $sql = $this->db_api->execute("UPDATE nodes SET conallow = 1, authtype = ? WHERE id = ?", array($authtype, $nodeid));
            $sql = $this->db_api->execute("DELETE FROM nodetype WHERE nodeid = ?", array($nodeid));

            foreach(explode(",", $data["nodetypes"]) AS $arrkey => $nodetype){
              $sql = $this->db_api->execute("INSERT INTO nodetype (id, nodeid, code) VALUES(NULL, ?, ?)", array($nodeid, $nodetype));
            }

            $returnmessage = array("status" => 0, "message" => "Successfully allowed connection for node with ID {$data["nodeid"]}.");
            $querydata = [];
            $querydata["data"]["acceptNodeRequest"] = $returnmessage;
            $querydata["nodeinfo"]["authhash"] = $data["authhash"];

            if(!is_null($server)){
              $server->messageSpecificNode($querydata);
            }else{
              $this->websocket_api = new WebSocket_Api();
              $this->websocket_api->sendToWSS("messageSpecificNode", $querydata);
            }

            return $returnmessage;
          }else{
            return $this->logging_api->getErrormessage("001");
          }
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("002", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }

    public function declineNodeRequest(array $data, array $loginData = NULL, $server = NULL){
      if(array_key_exists("nodeid", $data) && array_key_exists("authhash", $data)){
        try{
          $sql = $this->db_api->execute("UPDATE nodes SET conallow = 0 WHERE id = ?", array($data["nodeid"]));

          $returnmessage = array("status" => 0, "message" => "Successfully declined connection for id {$data["nodeid"]}.");
          $querydata = [];
          $querydata["data"]["declineNodeRequest"] = $returnmessage;
          $querydata["nodeinfo"]["authhash"] = $data["authhash"];

          if(!is_null($server)){
            print_r($server->messageSpecificNode($querydata));
          }else{
            $this->websocket_api = new WebSocket_Api();
            print_r($this->websocket_api->sendToWSS("messageSpecificNode", $querydata));
          }

          return $returnmessage;
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    public function loginStatus(array $data, array $loginData = NULL){
      if(array_key_exists("authhash", $loginData)){
        try{
          $sql = $this->db_api->execute("SELECT n.id, GROUP_CONCAT(nta.description SEPARATOR ', ') AS nodetype, n.hostname
          FROM nodes n
          JOIN nodetype nt ON nt.nodeid = n.id
          JOIN nodetypes_avail nta ON nta.code = nt.code
          WHERE n.nodeauthhash = ?
          GROUP BY n.id", array($this->encryption_api->encryptString($loginData["authhash"])));
          $sqldata = $sql->fetchAll(\PDO::FETCH_ASSOC)[0];

          return array("status" => 0, "method" => "loginStatus", "message" => "This node is logged in.", "data" => $sqldata);
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    public function updateScriptVersion(array $data, array $loginData = NULL){
      if(array_key_exists("authhash", $loginData) && array_key_exists("scriptversion", $data) && array_key_exists("chia", $data)){
        try{
          $sql = $this->db_api->execute("UPDATE nodes SET scriptversion = ?, chiaversion = ?, chiapath = ? WHERE nodeauthhash = ?", array($data["scriptversion"], $data["chia"]["version"], $data["chia"]["path"], $this->encryption_api->encryptString($loginData["authhash"])));

          return array("status" =>0, "message" => "Successfully updated version.");
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    public function nodeUpdateStatus(array $data, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        return array("status" => 0, "message" => "Successfully queried node update status", "data" => array("nodeid" => $nodeid, "status" => $data));
      }catch(Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    public function checkUpdatesAndChannels(array $data = [], array $loginData = NULL){
      $updatepackagepath = "https://files.chiamgmt.edtmair.at/client/";
      $version_file_json = file_get_contents("{$updatepackagepath}/versions.json");
      $version_file_data = json_decode($version_file_json, true);
      $returndata = [];
      $returndata["available_channels"] = array_keys($version_file_data);
      $returndata["updateinfos"] = [];


      try{
        if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"])){
          $sql = $this->db_api->execute("SELECT id, hostname, scriptversion, updatechannel, chiaversion FROM nodes WHERE authtype = 2 AND id = ?", array($data["nodeid"]));
        }else{
          $sql = $this->db_api->execute("SELECT id, hostname, scriptversion, updatechannel, chiaversion FROM nodes WHERE authtype = 2", array());
        }

        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $nodedata){
          $returndata["updateinfos"][$nodedata["id"]] = $nodedata;

          $returndata["updateinfos"][$nodedata["id"]]["updateavailable"] = 0;
          if(array_key_exists($nodedata["updatechannel"], $version_file_data)){
            $returndata["updateinfos"][$nodedata["id"]]["updateavailable"] = version_compare($nodedata["scriptversion"], $version_file_data[$nodedata["updatechannel"]][0]["version"]);
            $returndata["updateinfos"][$nodedata["id"]]["remoteversion"] = $version_file_data[$nodedata["updatechannel"]][0]["version"];
          }
        }

        return array("status" =>0, "message" => "Successfully loaded all requested data.", "data" => $returndata);
      }catch(Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    public function updateNodeBranch(array $data, array $loginData = NULL){
      if(array_key_exists("branch", $data) && array_key_exists("nodeid", $data)){
        $allowedbranches = array("dev","staging","main");
        if(in_array($data["branch"], $allowedbranches)){
          try{
            $sql = $this->db_api->execute("UPDATE nodes SET updatechannel = ? WHERE id = ?", array($data["branch"],$data["nodeid"]));

            return array("status" => 0, "message" => "Successfully updated branch for node {$data["nodeid"]} to {$data["branch"]}.", "data" => ["branch" => $data["branch"], "nodeid" => $data["nodeid"]]);
          }catch(Exception $e){
            return $this->logging_api->getErrormessage("001", $e);
          }
        }else{
          return $this->logging_api->getErrormessage("002", "Branch {$data["branch"]} not allowed.");
        }
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }

    public function queryNodesServicesStatus(array $data = [], array $loginData = NULL, $server = NULL){
      $allNodeStatus = $this->getNodeStatus($data);
      if($allNodeStatus["status"] > 0) return $allNodeStatus;

      $allNodeStatus = $allNodeStatus["data"];
      if(!is_null($server)){
        $activeSubscriptions = $server->getActiveSubscriptions($loginData)["getActiveSubscriptions"];
      }else{
        $this->websocket_api = new WebSocket_Api();
        $activeSubscriptions = $this->websocket_api->sendToWSS("getActiveSubscriptions")["getActiveSubscriptions"];
      }

      $foundnode = [];
      $datatosave = [];

      $now = new \DateTime();
      if($activeSubscriptions["status"] == 0 && array_key_exists("data", $activeSubscriptions)){
        foreach($activeSubscriptions["data"] AS $nodetype => $allnodesconnected){
          if($nodetype != "webClient" && $nodetype != "backendClient"){
            foreach($allnodesconnected AS $connid => $nodedata){
              if(array_key_exists($nodedata["nodeid"], $allNodeStatus) && !in_array($nodedata["nodeid"], $foundnode)){
                array_push($foundnode, $nodedata["nodeid"]);
              }
            }
          }
        }
      }else{
        return $activeSubscriptions;
      }

      //Onlinestatus: 0 = Disconnected, 1 = Connected, 2 = Querying
      foreach($allNodeStatus AS $arrkey => $nodedata){
        if(in_array($nodedata["nodeid"], $foundnode)){
          $onlinestatus = 0;
          $walletstatus = 2;
          $farmerstatus = 2;
          $harvesterstatus = 2;
        }else{
          $onlinestatus = 1;
          $walletstatus = 1;
          $farmerstatus = 1;
          $harvesterstatus = 1;
        }

        try{
          if(array_key_exists($nodedata["nodeid"], $allNodeStatus)){
            $querytime = new \DateTime($allNodeStatus[$nodedata["nodeid"]]["querytime"]);
            $querytime->modify('+30 seconds');

            //If the previous node status is not the current node state, safe new state
            if($now > $querytime || $allNodeStatus[$nodedata["nodeid"]]["onlinestatus"] != $onlinestatus){
              $this->queryDataFromNode($nodedata, $server);

              $sql = $this->db_api->execute("UPDATE nodes_status SET onlinestatus = ?, walletstatus = ?, farmerstatus = ?, harvesterstatus = ?, querytime = ? WHERE nodeid = ?",
                                            array($onlinestatus, $walletstatus, $farmerstatus, $harvesterstatus, $now->format("Y-m-d H:i:s"), $nodedata["nodeid"]));
            }
          }else{
            $this->queryDataFromNode($nodedata, $server);

            $sql = $this->db_api->execute("INSERT INTO nodes_status (id, nodeid, onlinestatus, walletstatus, farmerstatus, harvesterstatus, querytime)
                                            VALUES(NULL, ?, ?, ?, ?, ?, ?)",
                                            array($nodedata["nodeid"], $onlinestatus, $walletstatus, $farmerstatus, $harvesterstatus, $now->format("Y-m-d H:i:s")));
          }
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }

      return $this->getNodeStatus($data);
    }

    public function setNodeServiceStats(array $data = [], array $loginData = NULL, $server = NULL){
      if(array_key_exists("type", $data) && array_key_exists("stat", $data) && array_key_exists("nodeid", $data)){
        if(is_numeric($data["stat"]) && $data["type"] >= 3 && $data["type"] <= 5){
          if($data["type"] == 3){
            $updateCol = "farmerstatus";
          }else if($data["type"] == 4){
            $updateCol = "harvesterstatus";
          }else if($data["type"] == 5){
            $updateCol = "walletstatus";
          }

          $sql = $this->db_api->execute("UPDATE nodes_status SET $updateCol = ? WHERE nodeid = ?", array($data["stat"], $data["nodeid"]));

          return array("status" => 0, "message" => "Successfully updated $updateCol for node {$data["nodeid"]}.");
        }else{
          return $this->logging_api->getErrormessage("001", "Stat {$data["stat"]} no known.");
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    private function getNodeStatus(array $nodedata = []){
      if(count($nodedata) == 0){
        $sql = $this->db_api->execute("SELECT n.id AS nodeid, n.nodeauthhash FROM nodetype nt JOIN nodes n ON n.id = nt.nodeid WHERE code >= 3 AND code <= 5 GROUP by n.id", array());
        $nodedata = $sql->fetchAll(\PDO::FETCH_ASSOC);
      }

      $nodeids = [];
      if(count($nodedata) > 0){
        $or_statement = "WHERE ";
        for($i = 0; $i < count($nodedata); $i++){
          if(array_key_exists($i+1, $nodedata)){
            $or_statement .= "nodeid = ? OR ";
          }else{
            $or_statement .= "nodeid = ?";
          }
          array_push($nodeids, $nodedata[$i]["nodeid"]);
        }

        try{
          $sql = $this->db_api->execute("SELECT nodeid, onlinestatus, walletstatus, farmerstatus, harvesterstatus, querytime FROM nodes_status $or_statement", $nodeids);
          $sqreturn = $sql->fetchAll(\PDO::FETCH_ASSOC);

          for($i = 0; $i < count($sqreturn); $i++){
            $sqreturn[$sqreturn[$i]["nodeid"]] = $sqreturn[$i];
            unset($sqreturn[$i]);
          }
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        $sqreturn = [];
      }
      return array("status" => 0, "message" => "Successfully loaded requested node status.", "data" => $sqreturn);
    }

    private function queryDataFromNode(array $nodedata, $server = NULL){
      $sql = $this->db_api->execute("SELECT na.description, n.nodeauthhash FROM nodetype nt JOIN nodetypes_avail na ON na.code = nt.code JOIN nodes n ON n.id = nt.nodeid WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = ?", array($nodedata["nodeid"]));

      foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $infos){
        $querydata = [];
        $querydata["data"]["query" . $infos["description"] . "Status"] = array(
          "status" => 0,
          "message" => "Query " . $infos["description"] . " running status.",
          "data"=> array()
        );
        $querydata["nodeinfo"]["authhash"] = $this->encryption_api->decryptString($infos["nodeauthhash"]);
        if(!is_null($server)){
          $server->messageSpecificNode($querydata);
        }else{
          $this->websocket_api = new WebSocket_Api();
          $activeSubscriptions = $this->websocket_api->sendToWSS("messageSpecificNode", $querydata);
        }
      }
    }
  }
?>
