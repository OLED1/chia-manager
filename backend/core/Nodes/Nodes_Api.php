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
      $this->encryption_api = new Encryption_Api();
      $this->server = $server;
    }

    /**
     * Returns a list of active subscriptions known to the websocket server which saves this data locally.
     * Subscriptions contains a list of clients which are currently logged in and accepted by the api.
     * Function made for: Backend / Web GUI
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[List of nodes of active subscriptions]}
     */
    public function getActiveSubscriptions(): array
    {
      $this->websocket_api = new WebSocket_Api();
      return $this->websocket_api->sendToWSS("getActiveSubscriptions")["getActiveSubscriptions"];
    }

    /**
     * Returns a list of active requests known to the websocket server which saves this data locally.
     * Requests contains a list of clients which are currently waiting to get accepted by the api.
     * Function made for: Backend / Web GUI
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[List of nodes of active requests]}
     */
    public function getActiveRequests(): array
    {
      $this->websocket_api = new WebSocket_Api();
      return $this->websocket_api->sendToWSS("getActiveRequests")["getActiveRequests"];
    }

    /**
     * Returns an array of all information available for all nodes.
     * Function made for: Web GUI
     * @throws Exception $e       Throws an exception on db errors.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB stored node information]}
     */
    public function getConfiguredNodes(array $data = []): array
    {
      $nodeid = "";
      $nodetype = "";

      if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 0){
        $nodeid = "WHERE n.id = {$data["nodeid"]}";
      }

      if(array_key_exists("nodetypenum", $data) && (is_numeric($data["nodetypenum"]) || is_array($data["nodetypenum"]))){
        if(is_numeric($data["nodetypenum"] && $data["nodetypenum"] > 0 && $data["nodetypenum"] < 6)) $nodetype = "AND nt.code = {$data["nodetypenum"]}";
        else if(is_array($data["nodetypenum"])) $nodetype = "AND nt.code IN (" . implode(",", $data["nodetypenum"]) . ")";
      }

      $returndata = array();

      try{
        $sql = $this->db_api->execute("SELECT n.id, GROUP_CONCAT(nta.description SEPARATOR ', ') AS nodetype, n.nodeauthhash, n.authtype,
                                              n.conallow, n.hostname, n.scriptversion, n.chiaversion, n.chiapath, n.ipaddress,
                                              n.changeable, n.changedIP, MAX(cis.memory_total) AS memory_total, MAX(cis.swap_total) AS swap_total,
                                              MAX(cis.cpu_cores) AS cpu_cores, MAX(cis.cpu_count) AS cpu_count, MAX(cis.cpu_model) AS cpu_model, lastseen
                                      FROM nodes n
                                      JOIN nodetype nt ON nt.nodeid = n.id
                                      JOIN nodetypes_avail nta ON nta.code = nt.code {$nodetype}
                                      LEFT JOIN chia_infra_sysinfo cis ON cis.timestamp = (SELECT MAX(timestamp) FROM chia_infra_sysinfo WHERE nodeid = n.id) AND cis.nodeid = n.id
                                      {$nodeid}
                                      GROUP BY n.id", array());

        $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);
        $returnarray = array();

        foreach ($sqdata as $arrkey => $conninfo) {
          $returnarray[$conninfo["id"]] = $conninfo;
          $returnarray[$conninfo["id"]]["nodeauthhash"] = $this->encryption_api->decryptString($conninfo["nodeauthhash"]);
        }

        return array("status" => 0, "message" => "Sucessfully loaded all client data.", "data" => $returnarray);
      }
      catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    /**
     * Returns a list of all nodes known and registered to the api.
     * Function made for: Web GUI
     * @throws Exception $e       Throws an exception on db errors.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB stored node type information]}
     */
    public function getNodeTypes(): array
    {
      try{
        $sql = $this->db_api->execute("SELECT id, description, code, allowed_authtype, nodetype FROM nodetypes_avail WHERE selectable = 1", array());

        $returndata = array();
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $info){
          $returndata["by-id"][$info["id"]] = $info;
          $returndata["by-desc"][$info["description"]] = $info;
        }

        return array("status" => 0, "message" => "Sucessfully loaded all available nodetypes.", "data" => $returndata);
      }catch(\Exception $e){
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
    public function acceptIPChange(array $data, array $loginData = NULL, $server = NULL): array
    {
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
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }
    }

    /**
     * Accept a request of (newly) connected node.
     * Function made for: Web GUI
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { "nodeid" : [nodeid], "authhash" : [node's authhash], "nodetypes" : [The nodes types as array(e.g. [farmer, harvester])]}
     * @param  array $loginData                { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server     An instance to the Webscoket server to be able to communicate with the node
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function acceptNodeRequest(array $data, array $loginData = NULL, $server = NULL): array
    {
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
            $sql = $this->db_api->execute("REPLACE INTO nodes_status (id, nodeid, onlinestatus, walletstatus, farmerstatus, harvesterstatus) VALUES(NULL, ?, ?, ?, ?, ?)", array($nodeid, 2, 2, 2, 2));

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
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("002", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }

    /**
     * Decline a request of a (newly) connected node.
     * Function made for: Web GUI
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { "nodeid" : [nodeid], "authhash" : [node's authhash] }
     * @param  array $loginData                { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server     An instance to the Webscoket server to be able to communicate with the node.
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function declineNodeRequest(array $data, array $loginData = NULL, $server = NULL): array
    {
      if(array_key_exists("nodeid", $data) && array_key_exists("authhash", $data)){
        try{
          $sql = $this->db_api->execute("UPDATE nodes SET conallow = 0 WHERE id = ? AND changeable = 1 AND (conallow = 1 OR conallow = 2)", array($data["nodeid"]));

          $returnmessage = array("status" => 0, "message" => "Successfully declined connection for id {$data["nodeid"]}.");
          $querydata = [];
          $querydata["data"]["declineNodeRequest"] = $returnmessage;
          $querydata["nodeinfo"]["authhash"] = $data["authhash"];

          if(!is_null($server)){
            $server->messageSpecificNode($querydata);
          }else{
            $this->websocket_api = new WebSocket_Api();
            $this->websocket_api->sendToWSS("messageSpecificNode", $querydata);
          }

          return $returnmessage;
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Removes a node and all ist associated data from the database.
     * Function made for: Web GUI
     * @throws Exception $e                   Throws an exception on db errors.
     * @param  array  $data                   { "nodeid" : [nodeid], "authhash" : [node's authhash] }
     * @param  array $loginData               { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server    An instance to the Webscoket server to be able to communicate with the node
     * @return array                          {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function removeNodeAndData(array $data, array $loginData = NULL, $server = NULL): array
    {
      if(array_key_exists("nodeid", $data) && array_key_exists("authhash", $data)){
        try{
          $sql = $this->db_api->execute("SELECT changeable FROM nodes WHERE id = ?", array($data["nodeid"]));
          $sqldata = $sql->fetchAll(\PDO::FETCH_ASSOC);

          if(count($sqldata) == 1){
            $changeable = $sqldata[0]["changeable"];

            if($changeable){
              $this->db_api->execute("DELETE FROM nodes WHERE id = ?", array($data["nodeid"]));
              $this->db_api->execute("DELETE FROM nodes_status WHERE nodeid = ?", array($data["nodeid"]));
              $this->db_api->execute("DELETE FROM nodetype WHERE id = ?", array($data["nodeid"]));

              $returnmessage = array("status" => 0, "message" => "Successfully removed node {$data["nodeid"]}.", "data" => $data["nodeid"]);
              $querydata = [];
              $querydata["data"]["removeNodeAndData"] = $returnmessage;
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
          }else{
            return $this->logging_api->getErrormessage("002");
          }
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("003", $e);
        }
      }
    }

    /**
     * Returns the loginstatus of certain node with the given authhash.
     * Function made for: Web GUI
     * @throws Exception $e     Throws an exception on db errors.
     * @param  array  $data     { "authhash" : [A node's authhash]}
     * @param  array $loginData [description]
     * @return array            [description]
     */
    public function loginStatus(array $data, array $loginData = NULL): array
    {
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
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Sets the node client's script version to be accessible for the frontend
     * Function made for: Node client
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { "authhash" : [node's authhash - string], "scriptversion" : [Node's current node script version - string], "chia" : [Node's current installed chia version -string] }
     * @param  array $loginData                { NULL } No logindata needed to query this function.
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function updateScriptVersion(array $data, array $loginData = NULL): array
    {
      if(array_key_exists("authhash", $loginData) && array_key_exists("scriptversion", $data) && array_key_exists("chia", $data)){
        try{
          $sql = $this->db_api->execute("UPDATE nodes SET scriptversion = ?, chiaversion = ?, chiapath = ? WHERE nodeauthhash = ?", array($data["scriptversion"], $data["chia"]["version"], $data["chia"]["path"], $this->encryption_api->encryptString($loginData["authhash"])));

          return array("status" =>0, "message" => "Successfully updated version.");
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Sets the querieng nodes update status for the frontend.
     * Function made for: Node client
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { "status" : ["Current script update status" - array] }
     * @param  array $loginData                { "authhash" : [Node's authhash] }
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function nodeUpdateStatus(array $data, array $loginData = NULL): array
    {
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        return array("status" => 0, "message" => "Successfully queried node update status.", "data" => array("nodeid" => $nodeid, "status" => $data));
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    /**
     * Checks if there is an update available for a node.
     * Function made for: Web client
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { "nodeid" : ["A specific nodes id"] }
     * @param  array $loginData                { NULL } No loginData needed to query this function.
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" => ["Requested infos - array"]}
     */
    public function checkUpdatesAndChannels(array $data = [], array $loginData = NULL): array
    {
      $updatepackagepath = "https://files.chiamgmt.edtmair.at/client/";
      $version_file_json = file_get_contents("{$updatepackagepath}/versions.json");
      $version_file_data = json_decode($version_file_json, true);
      $returndata = [];
      $returndata["available_channels"] = array_keys($version_file_data);
      $returndata["updateinfos"] = [];

      //We need to use curl, because Amazon AWS wants a user Agent set to be able to download the chia release file
      $chiaversionspath = "https://api.github.com/repos/Chia-Network/chia-blockchain/releases";
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_URL, $chiaversionspath);
      curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:17.0) Gecko/20100101 Firefox/17.0');
      $chia_version_file_json = curl_exec($ch);
      curl_close($ch);
      $chia_version_file_data = json_decode($chia_version_file_json, true);

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
          $returndata["updateinfos"][$nodedata["id"]]["chiaupdateavail"] = 0;
          if(array_key_exists(0, $chia_version_file_data) && array_key_exists("name", $chia_version_file_data[0])){
            $returndata["updateinfos"][$nodedata["id"]]["chiaupdateavail"] = version_compare($nodedata["chiaversion"], $chia_version_file_data[0]["name"]);
          }
        }

        return array("status" =>0, "message" => "Successfully loaded all requested data.", "data" => $returndata);
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    /**
     * Updates the branch of a node client's scripts.
     * Function made for: Web GUI
     * @throws Exception $e     Throws an exception on db errors.
     * @param  array  $data     { "branch" : [The node client's target branch], "nodeid" : [The target node's id]}
     * @param  array $loginData { NULL } No $loginData needed to query this function.
     * @return array            {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : ["branch" => [branchname], "nodeid" => [nodeid]]}
     */
    public function updateNodeBranch(array $data, array $loginData = NULL): array
    {
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

    /**
     * Queries the current states of the chia nodes (Node UP/DOWN, Services running).
     * It refreshes the history data of the nodes and queries the current service stats from all clients.
     * Function made for: CronJob / Backend Client -> Chia Nodes
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { NULL } No data needed to query this function.
     * @param  array $loginData                { NULL } No loginData needed to query this function.
     * @param  Object $server     An instance to the Webscoket server to be able to communicate with the nodes directly.
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function queryNodesServicesStatus(array $data = [], array $loginData = NULL, Object $server = NULL): array
    {
      $client_nodes = $this->getConfiguredNodes(["nodetypenum" => [3,4,5]]);        
      if(!is_null($server)){
        $activeSubscriptions = $server->getActiveSubscriptions($loginData)["getActiveSubscriptions"];
      }else{
        $this->websocket_api = new WebSocket_Api();
        $activeSubscriptions = $this->websocket_api->sendToWSS("getActiveSubscriptions")["getActiveSubscriptions"];
      }

      if(array_key_exists("data", $activeSubscriptions)){
        foreach($client_nodes["data"] AS $nodeid => $nodedata){
          $found = false;
          foreach(explode(",",$nodedata["nodetype"]) AS $arrkey => $nodetype){
            if(array_key_exists($nodetype, $activeSubscriptions["data"])){
              foreach($activeSubscriptions["data"][$nodetype] AS $connectionnr => $conninfo){
                if($conninfo["nodeid"] == $nodeid){
                  $found = true;
                  $this->setNodeUpDown(["nodeid" => $nodeid, "updown" => 1]);
                  if(!is_null($server)){
                    $querydata = ["data" => ["get_chia_status" => ["status" => 0,"message" => "Query chia wallet/farmer/harvester running status.","data"=> []]],"nodeinfo" => ["authhash" => $nodedata["nodeauthhash"]]];
                    $this->server->messageSpecificNode($querydata);
                  }
                  break;
                }
              }
            }
            if($found) break;
          }
          if(!$found){
            $this->setNodeUpDown(["nodeid" => $nodeid, "updown" => 0]);
            $this->updateChiaStatus(["nodeid" => $nodeid, "farmer" => ["status" => 1], "wallet" => ["status" => 1], "harvester" => ["status" => 1]]);
          } 
        }
      }  
      
      return array("status" => 0, "message" => "Succesfully loaded active subscriptions and upstatus.", "data" => ["onlinestatus" => 0]);
    }

    /**
     * Changes the Nodes Upstatus in the database. Informs the frontend about changes.
     * 0 = Node DOWN, 1 = Node UP
     * @param array $data   { "nodeid" : [The systems node id], "updown" : [0=Node Down/1=Node UP] }
     * @return array        Returnes the current status information stored in the database.
     */
    public function setNodeUpDown(array $data): array
    {
      if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 0 && array_key_exists("updown", $data) && is_numeric($data["updown"]) && ($data["updown"] == 0 || $data["updown"] == 1)){
        try{
          $sql = $this->db_api->execute("SELECT id, nodeid, onlinestatus, lastreported FROM nodes_up_status WHERE nodeid = ? ORDER BY firstreported DESC LIMIT 1", array($data["nodeid"]));
          $founddata = $sql->fetchAll(\PDO::FETCH_ASSOC);

          /*echo json_encode($founddata, JSON_PRETTY_PRINT);
          echo json_encode($data, JSON_PRETTY_PRINT);*/

          if(!array_key_exists(0, $founddata) || $founddata[0]["onlinestatus"] != $data["updown"]){
            //echo "Onlinestatus for node {$data["nodeid"]} changed from {$founddata[0]["onlinestatus"]} to {$data["updown"]}.\n";
            $this->db_api->execute("INSERT INTO nodes_up_status (id, nodeid, onlinestatus, firstreported, lastreported) VALUES(NULL, ?, ?, current_timestamp(), current_timestamp())", array($data["nodeid"], $data["updown"]));
          }
          //echo "Updating existing entry for node {$data["nodeid"]}, state: {$founddata[0]["onlinestatus"]}.\n";
          if(count($founddata) == 1) $this->db_api->execute("UPDATE nodes_up_status SET lastreported = current_timestamp() WHERE id = ?", array($founddata[0]["id"]));
        }catch(\Exception $e){
          //@TODO Implement correct status code
          return array("status" => 1, "message" => "An error occured. {$e->getMessage()}");
        }

        return array("status" => 0, "message" => "Succesfully loaded active subscriptions and upstatus.", "data" => []);
      }else{
        //@TODO Implement correct status code
        return array("status" => 1, "message" => "Not all data stated.");
      }
    }

    /**
     * Changes the Nodes Services Upstatus in the database. Informs the frontend about changes.
     * Data sent in status: 
     * Service Status: 0 = Service DOWN, 1 = Service UP
     * Service ID's: 3 = Farmer, 4 = Harvester, 5 = Wallet
     * @param array $data         { "nodeid" : [The systems node id], "wallet" : { "status" => [0=Service Down/1=Service UP] }, "farmer" : { "status" => [0=Service Down/1=Service UP] }, "harvester" : { "status" => [0=Service Down/1=Service UP] }}
     * @param array $loginData   { "authhash" => [The node's authhash] } *Must be set when no nodeid is set in $data  
     * @return array              Returnes the current status information stored in the database.
     */
    public function updateChiaStatus(array $data, array $loginData = NULL): array
    {
      try{
        if(array_key_exists("wallet", $data) && array_key_exists("farmer", $data) && array_key_exists("harvester", $data)){
          $nodeid = NULL;
          if(array_key_exists("nodeid", $data) && is_int($data["nodeid"]) && $data["nodeid"] > 0) $nodeid = $data["nodeid"];
          else if(!is_null($loginData) && array_key_exists("authhash", $loginData) && is_string($loginData["authhash"])){
            $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
            $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];
          }
  
          if(!is_null($nodeid) && $nodeid > 0){        
            $sql = $this->db_api->execute("SELECT nt.nodeid, n.hostname, n.nodeauthhash, nta.code AS serviceid, LOWER(nta.description) AS description, nss.id AS curr_service_state_id, nss.servicestate, nss.firstreported, nss.lastreported
                                            FROM nodetype nt
                                            LEFT JOIN nodes n ON n.id = nt.nodeid
                                            LEFT JOIN LATERAL (
                                              SELECT id, nodeid, serviceid, servicestate, firstreported, lastreported FROM nodes_services_status WHERE nodeid = n.id AND serviceid = nt.code ORDER BY firstreported DESC LIMIT 1
                                            ) AS nss
                                            ON nss.serviceid = nt.code
                                            JOIN nodetypes_avail nta ON nta.code = nt.code
                                            WHERE nt.code IN (3,4,5) AND n.id = ?", array($nodeid));

            $founddata = $sql->fetchAll(\PDO::FETCH_ASSOC);
            if(count($founddata) > 0){
              //print_r(json_encode($founddata, JSON_PRETTY_PRINT));
              foreach($founddata AS $arrkey => $savedstates){
                $reported_service_state = intval(!boolval($data[$savedstates["description"]]["status"]));
                if((is_numeric($savedstates["servicestate"]) || is_null($savedstates["servicestate"])) && array_key_exists($savedstates["description"], $data) && (($reported_service_state != $savedstates["servicestate"]) || is_null($savedstates["servicestate"]))){
                  $this->db_api->execute("INSERT INTO nodes_services_status (id, nodeid, serviceid, servicestate, firstreported, lastreported) VALUES(NULL, ?, ?, ?, current_timestamp(), current_timestamp())", array($nodeid, $savedstates["serviceid"], $reported_service_state));
                }
                if(is_numeric($savedstates["servicestate"])){
                  $this->db_api->execute("UPDATE nodes_services_status SET lastreported = current_timestamp() WHERE id = ?", array($savedstates["curr_service_state_id"]));
                }
              }
            }else{
              //@TODO Implement correct status code
              return array("status" => 1, "message" => "This node has no chia services registered.");
            }
  
            return array("status" => 0, "message" => "Succesfully loaded active subscriptions and upstatus.", "data" => []);
          }else{
            //@TODO Implement correct status code
            return array("status" => 1, "message" => "No valid nodeids found.");
          }
        }else{
          //@TODO Implement correct status code
          return array("status" => 1, "message" => "Not all data stated.");
        }
      }catch(\Exception $e){
        //@TODO Implement correct status code
        print_r($e->getMessage());
        return array("status" => 1, "message" => "An error occured. {$e->getMessage()}");
      }
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @param [type] $loginData
     * @param [type] $server
     * @return array
     */
    public function getCurrentChiaNodesUPAndServiceStatus(array $data = [], array $loginData = NULL, $server = NULL): array
    {
      try{
        $nodeid = NULL;
        $nodetypes = NULL;

        //if(array_key_exists("nodeid", $data) && is_int($data["nodeid"]) && $data["nodeid"] > 0) $nodeid = $data["nodeid"];
        //if(array_key_exists("nodetypes", $data) && (is_array($data["nodetypes"]) || is_string($data["nodetypes"])) $nodetypes = 

        $sql = $this->db_api->execute("SELECT nt.nodeid, n.hostname, nus.onlinestatus, nus.firstreported AS node_firstreported, nus.lastreported AS node_lastreported, nta.description, nss.serviceid, nss.servicestate, nss.firstreported AS service_firstreported, nss.lastreported AS service_lastreported
                                        FROM nodetype nt
                                        INNER JOIN nodes n ON n.id = nt.nodeid
                                        INNER JOIN LATERAL (
                                          SELECT nodeid, onlinestatus, firstreported, lastreported FROM nodes_up_status WHERE nodeid = n.id ORDER BY firstreported DESC, lastreported DESC LIMIT 1
                                        ) AS nus ON nus.nodeid = nt.nodeid
                                        INNER JOIN LATERAL (
                                          SELECT id, nodeid, serviceid, servicestate, firstreported, lastreported FROM nodes_services_status WHERE nodeid = n.id AND serviceid = nt.code ORDER BY firstreported DESC, lastreported DESC LIMIT 1
                                        ) AS nss
                                        INNER JOIN nodetypes_avail nta ON nta.code = nt.code
                                        WHERE nt.code IN (3,4,5)", array());

        $returndata = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $serviceinfo){
          if(!array_key_exists($serviceinfo["nodeid"], $returndata)){
            $returndata[$serviceinfo["nodeid"]] = [
              "nodeinfo" => [
                "nodeid" => $serviceinfo["nodeid"],
                "hostname" => $serviceinfo["hostname"]
              ],
              "onlinestatus" => [
                "status" => $serviceinfo["onlinestatus"],
                "node_firstreported" => $serviceinfo["node_firstreported"],
                "node_lastreported" => $serviceinfo["node_lastreported"]
              ],
              "services" => []
            ];
          }
          $returndata[$serviceinfo["nodeid"]]["services"][$serviceinfo["serviceid"]] = [
            "servicestate" => $serviceinfo["servicestate"],
            "service_desc" => $serviceinfo["description"],
            "service_firstreported" => $serviceinfo["service_firstreported"],
            "service_lastreported" => $serviceinfo["service_lastreported"]
          ];
        }

        return array("status" => 0, "message" => "Succesfully loaded active subscriptions and upstatus.", "data" => $returndata);
      }catch(\Exception $e){
        //@TODO Implement correct status code
        print_r($e->getMessage());
        return array("status" => 1, "message" => "An error occured. {$e->getMessage()}");
      }
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @param [type] $loginData
     * @param [type] $server
     * @return array
     */
    public function getCurrentChiaNodesStatusHistory(array $data, array $loginData = NULL, $server = NULL):array
    {
      return array("status" => 0, "message" => "Succesfully loaded active subscriptions and upstatus.", "data" => []);
    }
  }
?>
